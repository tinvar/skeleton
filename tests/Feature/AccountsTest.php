<?php

declare(strict_types=1);

/**
 * The accounts (ADR 0153), through the whole PHP request path -- the
 * session middleware, CSRF, the actions' throttles and validation, the
 * loaders -- and the queue worker, with the mail faked and read back.
 *
 * Signing up by a link to the address, in and out, by a code where
 * two-factor is on; a forgotten password and a changed one; a change of
 * address by a link to the new one; and sign out everywhere -- with the
 * refusals that matter: an address with an account answered as one
 * without, a link used twice, a code used twice, a session ended
 * elsewhere.
 */

namespace Tests\Feature\Accounts;

use App\Database\Migrations;
use App\Database\Models\Account\User;
use App\Kernel;
use App\Mail\Account\AccountMails;
use Tinvar\Accounts\Accounts;
use Tinvar\Accounts\TwoFactor\Base32;
use Tinvar\Accounts\TwoFactor\Totp;
use Tinvar\Console\Output;
use Tinvar\Data\Encoder;
use Tinvar\Http\ArrayThrottleStore;
use Tinvar\Http\CsrfTokens;
use Tinvar\Http\Request;
use Tinvar\Http\Response;
use Tinvar\Mail\SendMail;
use Tinvar\Observability\Logger;
use Tinvar\Observability\MemorySink;
use Tinvar\Observability\Telemetry;
use Tinvar\Routing\LoadedRoute;
use Tinvar\Support\Signer;
use Tinvar\Testing\Fakes\MailFake;
use Tinvar\Testing\Fakes\SentMail;

const KEY = 'skeleton-accounts-test-key-of-32-bytes!!';
const ORIGIN = 'https://app.test';
const PASSWORD = 'correct horse battery staple';

/** A browser of one: a cookie jar, an address, and the Kernel it talks to. */
final class Browser
{
    /** @var array<string, string> */
    public array $cookies = [];

    /** What the last page loaded handed its view, encoded as the renderer is handed it. */
    public mixed $props = null;

    public function __construct(public readonly Kernel $kernel, public readonly string $address = '203.0.113.10') {}

    public function get(string $url): Response
    {
        [$path, $query] = array_pad(explode('?', $url, 2), 2, null);
        parse_str((string) $query, $parsed);
        $this->props = null;
        $encoder = new Encoder();

        return $this->keep($this->kernel->handleWith(
            new Request('GET', $path, ['host' => 'app.test', 'accept' => 'text/html'], $parsed, [], $this->cookies, $query, $this->address),
            function (LoadedRoute $loaded, Request $passed) use ($encoder): Response {
                $this->props = $this->kernel->orm->readOnly(static fn (): array => $loaded->encode($encoder, $passed));

                return Response::make(200, '', ['content-type' => 'text/html; charset=utf-8']);
            },
        ));
    }

    /** @param array<string, mixed> $body */
    public function action(string $name, array $body = []): Response
    {
        // A form is posted from a page, which gave the browser its session.
        if (!isset($this->cookies[Kernel::SESSION_COOKIE])) {
            $this->get('/sign-in');
        }
        $token = (string) new CsrfTokens(
            new Signer(KEY, CsrfTokens::PURPOSE),
            fn (Request $r): string => $this->kernel->accounts->sessionId($r),
        )->for(new Request('GET', '/', ['host' => 'app.test'], [], [], $this->cookies));

        return $this->keep($this->kernel->handle(
            new Request(
                'POST',
                "/_action/{$name}",
                ['host' => 'app.test', 'content-type' => 'application/json', 'accept' => 'application/json', 'x-xsrf-token' => $token],
                [],
                [],
                [...$this->cookies, '__Host-XSRF-TOKEN' => $token],
                null,
                $this->address,
            ),
            json_encode($body, JSON_THROW_ON_ERROR),
        ));
    }

    /** The page's data, as the view would read it. */
    public function page(string $key): mixed
    {
        return \is_array($this->props) ? ($this->props['page'][$key] ?? null) : null;
    }

    private function keep(Response $response): Response
    {
        foreach ($response->cookies() as $name => $line) {
            $this->cookies[$name] = explode(';', substr($line, \strlen($name) + 1))[0];
        }

        return $response;
    }
}

function app(MailFake $mail): Kernel
{
    $kernel = Kernel::fromEnvironment(
        key: KEY,
        database: ':memory:',
        telemetry: new Telemetry(new Logger(new MemorySink())),
        mailRenderer: $mail,
        mailTransport: $mail,
        signInStore: new ArrayThrottleStore(throttleClock()),
        origin: ORIGIN,
    );
    Migrations::migrate($kernel->orm->connection());

    return $kernel;
}

/** A throttle store's clock held still, so every hit a test makes is in one window. */
function throttleClock(): \Closure
{
    $at = time();

    return static fn (): int => $at;
}

/** Works the mail queue, as `bin/tinvar queue:work` does. */
function work(Kernel $kernel): void
{
    $output = new Output(static function (string $line): void {}, static function (string $line): void {});
    expect($kernel->console->run('queue:work', ['--queue=' . SendMail::QUEUE, '--stop-when-empty'], $output))->toBe(0);
}

/** The link the last email of $template to $address carries, as a path and query on the app. */
function link(MailFake $mail, string $template, string $address): string
{
    $sent = array_values(array_filter($mail->sent($template), static fn (SentMail $m): bool => $m->isTo($address)));
    expect($sent)->not->toBe([]);
    $url = (string) end($sent)->props['url'];
    expect($url)->toStartWith(ORIGIN . '/');

    return substr($url, \strlen(ORIGIN));
}

function tokenOf(string $path): string
{
    parse_str((string) parse_url($path, PHP_URL_QUERY), $query);

    return (string) ($query['token'] ?? '');
}

/** Signs up $email through the two steps and the inbox, and returns the browser, signed in. */
function signUp(Kernel $kernel, MailFake $mail, string $email, string $name = 'Ada Lovelace'): Browser
{
    $browser = new Browser($kernel);
    expect($browser->action('account.requestSignUp', ['email' => $email, 'name' => $name])->status)->toBe(303);
    work($kernel);
    $path = link($mail, AccountMails::SIGN_UP, $email);
    $browser->get($path);
    expect($browser->action('account.completeSignUp', ['token' => tokenOf($path), 'password' => PASSWORD])->status)->toBe(303);

    return $browser;
}

function signedInAs(Browser $browser): ?string
{
    $browser->get('/account');

    return \is_string($browser->page('email')) ? $browser->page('email') : null;
}

beforeEach(function () {
    $this->mail = new MailFake();
    $this->kernel = app($this->mail);
});

it('signs up by a link to the address, and makes no account until it is used', function () {
    $browser = new Browser($this->kernel);
    $browser->action('account.requestSignUp', ['email' => 'Ada@Example.com', 'name' => 'Ada Lovelace']);
    expect($this->kernel->orm->connection()->select('SELECT COUNT(*) AS n FROM users')[0]['n'])->toBe(0);

    work($this->kernel);
    $path = link($this->mail, AccountMails::SIGN_UP, 'ada@example.com');

    // The page only looks: opening it makes nothing.
    $browser->get($path);
    expect($browser->page('email'))->toBe('ada@example.com')
        ->and($this->kernel->orm->connection()->select('SELECT COUNT(*) AS n FROM users')[0]['n'])->toBe(0);

    // A password the policy refuses leaves the link unused.
    expect($browser->action('account.completeSignUp', ['token' => tokenOf($path), 'password' => 'short'])->status)->toBe(422);
    expect($browser->action('account.completeSignUp', ['token' => tokenOf($path), 'password' => PASSWORD])->status)->toBe(303)
        ->and(signedInAs($browser))->toBe('ada@example.com');

    // Used once: the same link again is refused.
    $again = new Browser($this->kernel);
    expect($again->action('account.completeSignUp', ['token' => tokenOf($path), 'password' => PASSWORD])->status)->toBe(422);
});

it('answers a sign-up for an address with an account as it answers any, and tells the owner', function () {
    signUp($this->kernel, $this->mail, 'ada@example.com');
    $before = \count($this->mail->sent(AccountMails::SIGN_UP));

    $stranger = new Browser($this->kernel, '198.51.100.7');
    $response = $stranger->action('account.requestSignUp', ['email' => 'ada@example.com', 'name' => 'Not Ada']);
    $fresh = $stranger->action('account.requestSignUp', ['email' => 'nobody@example.com', 'name' => 'Nobody']);

    expect($response->status)->toBe($fresh->status)
        ->and($response->header('location'))->toBe($fresh->header('location'));
    work($this->kernel);
    expect(\count($this->mail->sent(AccountMails::SIGN_UP)))->toBe($before + 1)
        ->and(array_filter($this->mail->sent(AccountMails::ALREADY_REGISTERED), static fn (SentMail $m): bool => $m->isTo('ada@example.com')))->not->toBe([]);
});

it('signs in and out, with one message for a wrong password and an unknown address', function () {
    signUp($this->kernel, $this->mail, 'ada@example.com');
    $browser = new Browser($this->kernel);

    $wrong = $browser->action('account.signIn', ['email' => 'ada@example.com', 'password' => 'not the password']);
    $unknown = $browser->action('account.signIn', ['email' => 'nobody@example.com', 'password' => 'not the password']);
    expect($wrong->status)->toBe(422)
        ->and($unknown->body())->toBe($wrong->body())
        ->and($wrong->body())->toContain(json_encode(Accounts::NO_MATCH, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

    $before = $browser->cookies[Kernel::SESSION_COOKIE];
    expect($browser->action('account.signIn', ['email' => 'ADA@example.com', 'password' => PASSWORD, 'next' => '//evil.example'])->header('location'))->toBe('/account')
        ->and($browser->cookies[Kernel::SESSION_COOKIE])->not->toBe($before)
        ->and(signedInAs($browser))->toBe('ada@example.com');

    expect($browser->action('account.signOut')->status)->toBe(200)
        ->and(signedInAs($browser))->toBeNull();
});

it('counts password guesses, and pauses an account guessed at from one address', function () {
    signUp($this->kernel, $this->mail, 'ada@example.com');
    $guesser = new Browser($this->kernel, '198.51.100.9');
    for ($i = 0; $i < 10; $i++) {
        $guesser->action('account.signIn', ['email' => 'ada@example.com', 'password' => "guess {$i}"]);
    }
    expect($guesser->action('account.signIn', ['email' => 'ada@example.com', 'password' => PASSWORD])->status)->toBe(429);

    // The owner, from anywhere else, is not.
    $owner = new Browser($this->kernel, '192.0.2.44');
    expect($owner->action('account.signIn', ['email' => 'ada@example.com', 'password' => PASSWORD])->status)->toBe(303);
});

it('resets a forgotten password by a link, ending every other session', function () {
    $other = signUp($this->kernel, $this->mail, 'ada@example.com');
    $browser = new Browser($this->kernel);

    $known = $browser->action('account.requestPasswordReset', ['email' => 'ada@example.com']);
    $unknown = $browser->action('account.requestPasswordReset', ['email' => 'nobody@example.com']);
    expect($known->header('location'))->toBe($unknown->header('location'));
    work($this->kernel);
    expect($this->mail->sent(AccountMails::PASSWORD_RESET))->toHaveCount(1);

    $path = link($this->mail, AccountMails::PASSWORD_RESET, 'ada@example.com');
    $browser->get($path);
    expect($browser->page('email'))->toBe('ada@example.com');
    expect($browser->action('account.resetPassword', ['token' => tokenOf($path), 'password' => 'a brand new passphrase'])->header('location'))->toBe('/account')
        ->and(signedInAs($browser))->toBe('ada@example.com')
        ->and(signedInAs($other))->toBeNull();

    work($this->kernel);
    $this->mail->assertSentTo('ada@example.com', AccountMails::PASSWORD_CHANGED);
    expect($browser->action('account.resetPassword', ['token' => tokenOf($path), 'password' => 'yet another passphrase'])->status)->toBe(422);
});

it('changes a known password, asking for the current one, and keeps this browser signed in', function () {
    $browser = signUp($this->kernel, $this->mail, 'ada@example.com');
    $other = new Browser($this->kernel, '192.0.2.50');
    $other->action('account.signIn', ['email' => 'ada@example.com', 'password' => PASSWORD]);

    expect($browser->action('account.changePassword', ['currentPassword' => 'wrong', 'password' => 'a brand new passphrase'])->status)->toBe(422);
    expect($browser->action('account.changePassword', ['currentPassword' => PASSWORD, 'password' => 'a brand new passphrase'])->status)->toBe(303)
        ->and(signedInAs($browser))->toBe('ada@example.com')
        ->and(signedInAs($other))->toBeNull();

    $fresh = new Browser($this->kernel);
    expect($fresh->action('account.signIn', ['email' => 'ada@example.com', 'password' => 'a brand new passphrase'])->status)->toBe(303);
});

it('moves an account to a new address only from a link the new address got', function () {
    $browser = signUp($this->kernel, $this->mail, 'ada@example.com');
    signUp($this->kernel, $this->mail, 'taken@example.com', 'Someone Else');

    expect($browser->action('account.requestEmailChange', ['email' => 'ada@new.example', 'currentPassword' => 'wrong'])->status)->toBe(422);
    $browser->action('account.requestEmailChange', ['email' => 'ada@new.example', 'currentPassword' => PASSWORD]);
    // One taken: answered the same, and nothing is sent to it.
    $browser->action('account.requestEmailChange', ['email' => 'taken@example.com', 'currentPassword' => PASSWORD]);
    work($this->kernel);
    expect(array_filter($this->mail->sent(AccountMails::EMAIL_CHANGE), static fn (SentMail $m): bool => $m->isTo('taken@example.com')))->toBe([])
        ->and(signedInAs($browser))->toBe('ada@example.com');

    $path = link($this->mail, AccountMails::EMAIL_CHANGE, 'ada@new.example');
    $phone = new Browser($this->kernel, '192.0.2.77');
    $phone->get($path);
    expect($phone->page('newEmail'))->toBe('ada@new.example');
    expect($phone->action('account.confirmEmailChange', ['token' => tokenOf($path)])->status)->toBe(303);

    work($this->kernel);
    $this->mail->assertSentTo('ada@example.com', AccountMails::EMAIL_CHANGED);
    // The epoch moved: the browser that asked was signed in as the account,
    // and is now signed out, its tokens no longer counting; the new address signs in.
    expect(new Browser($this->kernel)->action('account.signIn', ['email' => 'ada@new.example', 'password' => PASSWORD])->status)->toBe(303);
});

it('turns two-factor on with a code, then asks for one at sign-in, each good once', function () {
    $browser = signUp($this->kernel, $this->mail, 'ada@example.com');
    $browser->action('account.beginTwoFactor');
    $browser->get('/account');
    $secret = Base32::decode((string) ($browser->page('twoFactorSetup')['secret'] ?? ''));
    expect($secret)->toBeString();

    $confirmed = $browser->action('account.confirmTwoFactor', ['code' => Totp::at((string) $secret, time())]);
    expect($confirmed->status)->toBe(200);
    $codes = json_decode($confirmed->body(), true)['value']['recoveryCodes'] ?? [];
    expect($codes)->toHaveCount(10);

    $phone = new Browser($this->kernel, '192.0.2.80');
    expect($phone->action('account.signIn', ['email' => 'ada@example.com', 'password' => PASSWORD])->header('location'))->toStartWith('/sign-in/two-factor')
        ->and(signedInAs($phone))->toBeNull();
    $phone->get('/sign-in/two-factor');
    expect($phone->page('waiting'))->toBeTrue();

    // The code just used to confirm is spent; a recovery code works, once.
    expect($phone->action('account.completeSecondFactor', ['code' => Totp::at((string) $secret, time())])->status)->toBe(422);
    expect($phone->action('account.completeSecondFactor', ['code' => strtoupper($codes[0])])->status)->toBe(303)
        ->and(signedInAs($phone))->toBe('ada@example.com');

    $laptop = new Browser($this->kernel, '192.0.2.81');
    $laptop->action('account.signIn', ['email' => 'ada@example.com', 'password' => PASSWORD]);
    expect($laptop->action('account.completeSecondFactor', ['code' => $codes[0]])->status)->toBe(422);
});

it('signs out everywhere, and a session ended elsewhere signs nobody in', function () {
    $browser = signUp($this->kernel, $this->mail, 'ada@example.com');
    $other = new Browser($this->kernel, '192.0.2.90');
    $other->action('account.signIn', ['email' => 'ada@example.com', 'password' => PASSWORD]);
    expect(signedInAs($other))->toBe('ada@example.com');

    expect($browser->action('account.signOutEverywhere')->status)->toBe(200)
        ->and(signedInAs($browser))->toBeNull()
        ->and(signedInAs($other))->toBeNull()
        ->and($this->kernel->orm->connection()->select('SELECT COUNT(*) AS n FROM sessions')[0]['n'])->toBe(0);
});

it('sends a visitor to sign in from the account page, and back after', function () {
    $browser = new Browser($this->kernel);
    expect($browser->get('/account')->header('location'))->toBe('/sign-in?next=%2Faccount');

    $this->kernel->orm->save(tap(new User(), static function (User $user): void {
        $user->email = 'ada@example.com';
        $user->name = 'Ada';
        $user->passwordHash = password_hash(PASSWORD, PASSWORD_DEFAULT);
    }));
    expect($browser->action('account.signIn', ['email' => 'ada@example.com', 'password' => PASSWORD, 'next' => '/account'])->header('location'))->toBe('/account');
});

/**
 * @template T of object
 * @param T $value
 * @param \Closure(T): void $with
 * @return T
 */
function tap(object $value, \Closure $with): object
{
    $with($value);

    return $value;
}
