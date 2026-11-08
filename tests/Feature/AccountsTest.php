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

use App\Auth\Account\Users;
use App\Database\Migrations;
use App\Database\Models\Account\User;
use App\Kernel;
use App\Mail\Account\AccountMails;
use Tinvar\Accounts\AccountMailKind;
use Tinvar\Accounts\Accounts;
use Tinvar\Accounts\Passkeys\PasskeyStore;
use Tinvar\Accounts\Passkeys\VerifiedRegistration;
use Tinvar\Accounts\SendAccountMail;
use Tinvar\Accounts\SessionFactors;
use Tinvar\Accounts\SignInFactor;
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

/** A call a script makes to the app's API with an API token: no cookie, a bearer token alone. */
function api(Kernel $kernel, string $token, string $operation, array $body = []): Response
{
    return $kernel->handle(
        new Request('POST', "/_api/{$operation}", ['host' => 'app.test', 'content-type' => 'application/json', 'authorization' => "Bearer {$token}"], remoteAddress: '198.51.100.40'),
        json_encode((object) $body, JSON_THROW_ON_ERROR),
    );
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

// The kit stores a rehash through Users, over the hash its sign-in checked
// and no other (ADR 0168): a password stored since, a reset's, stays -- and
// what is stored is read past the user the request loaded.
it('stores a rehash only over the hash its sign-in checked, and reads the hash stored past the user loaded', function () {
    signUp($this->kernel, $this->mail, 'ada@example.com');
    $users = new Users($this->kernel->orm);
    $ada = $users->findByEmail('ada@example.com');
    \assert($ada !== null);
    $checked = $ada->accountPasswordHash();
    $reset = password_hash('a brand new passphrase', PASSWORD_DEFAULT);
    $this->kernel->orm->connection()->execute('UPDATE users SET password_hash = ?', [$reset]);

    expect($users->changePasswordFrom($ada, $checked, password_hash(PASSWORD, PASSWORD_DEFAULT)))->toBeFalse()
        ->and($users->storedPasswordHash($ada))->toBe($reset);

    $rehashed = password_hash('a brand new passphrase', PASSWORD_DEFAULT);
    expect($users->changePasswordFrom($ada, $reset, $rehashed))->toBeTrue()
        ->and($users->storedPasswordHash($ada))->toBe($rehashed);
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

it('asks a signed-in session to confirm it is its owner before adding a passkey or turning two-factor on', function () {
    // A stolen session cookie: signed in, and nothing more.
    $thief = signUp($this->kernel, $this->mail, 'ada@example.com');
    $confirmFirst = json_encode(Accounts::CONFIRM_FIRST, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    foreach (['account.passkeyOptions' => [], 'account.beginTwoFactor' => [], 'account.confirmTwoFactor' => ['code' => '123456']] as $action => $body) {
        $refused = $thief->action($action, $body);
        expect($refused->status)->toBe(422, $action)
            ->and($refused->body())->toContain($confirmFirst);
    }
    $thief->get('/account');
    expect($thief->page('confirmedUntil'))->toBeNull()
        ->and($thief->page('twoFactorSetup'))->toBeNull();
    expect($thief->action('account.confirmWithPassword', ['currentPassword' => 'a guess'])->status)->toBe(422)
        ->and($thief->action('account.passkeyOptions')->status)->toBe(422);

    // The owner, typing the password again: the account page says until when.
    $owner = $thief;
    expect($owner->action('account.confirmWithPassword', ['currentPassword' => PASSWORD])->status)->toBe(200);
    $owner->get('/account');
    expect($owner->page('confirmedUntil'))->toBeInt()->toBeGreaterThan(time());
    $options = $owner->action('account.passkeyOptions');
    expect($options->status)->toBe(200)
        ->and(json_decode($options->body(), true)['value']['rp']['id'] ?? null)->toBe('app.test');

    // Confirmed on one device is confirmed on that one alone.
    $phone = new Browser($this->kernel, '192.0.2.60');
    $phone->action('account.signIn', ['email' => 'ada@example.com', 'password' => PASSWORD]);
    expect($phone->action('account.beginTwoFactor')->status)->toBe(422);
});

it('tells the owner by email when a passkey is added, with the page to remove it from', function () {
    signUp($this->kernel, $this->mail, 'ada@example.com');
    $id = (string) $this->kernel->orm->connection()->select('SELECT id FROM users')[0]['id'];
    $this->kernel->accounts->accounts->deliver(new SendAccountMail(AccountMailKind::PasskeyAdded, name: 'Laptop', userId: $id));

    $sent = $this->mail->sent(AccountMails::PASSKEY_ADDED);
    expect($sent)->toHaveCount(1)
        ->and($sent[0]->isTo('ada@example.com'))->toBeTrue()
        ->and($sent[0]->props['url'])->toBe(ORIGIN . '/account')
        ->and($sent[0]->props['secondaryUrl'])->toBe(ORIGIN . '/password/forgot')
        ->and($sent[0]->props['passkey'])->toBe('Laptop');
});

it('asks a signed-in session to confirm it is its owner before removing a passkey, and tells the owner', function () {
    $browser = signUp($this->kernel, $this->mail, 'ada@example.com');
    $id = (string) $this->kernel->orm->connection()->select('SELECT id FROM users')[0]['id'];
    // A passkey as one added earlier would be kept: the ceremony is the browser test's.
    $store = new PasskeyStore($this->kernel->orm->connection());
    $store->add($id, random_bytes(32), new VerifiedRegistration(random_bytes(16), 'key', -7, 0, str_repeat("\0", 16), true, false, false, 'none'), 'Laptop', []);
    $passkey = $store->of($id)[0]->id;

    $refused = $browser->action('account.removePasskey', ['id' => $passkey]);
    expect($refused->status)->toBe(422)
        ->and($refused->body())->toContain(json_encode(Accounts::CONFIRM_FIRST, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE))
        ->and($this->kernel->orm->connection()->select('SELECT COUNT(*) AS n FROM passkeys')[0]['n'])->toBe(1);

    // The password proves one factor: an account with a passkey changes its
    // second factors from a session of two (ADR 0184).
    $browser->action('account.confirmWithPassword', ['currentPassword' => PASSWORD]);
    $one = $browser->action('account.removePasskey', ['id' => $passkey]);
    expect($one->status)->toBe(422)
        ->and($one->body())->toContain(json_encode(Accounts::CONFIRM_SECOND_FACTOR, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

    // Signed in with the passkey, as its ceremony would: the browser test's.
    $user = new Users($this->kernel->orm)->find($id) ?? throw new \LogicException('No account.');
    $browser->cookies = [...$browser->cookies, ...$this->kernel->accounts->signedIn($user, new Request('GET', '/', ['host' => 'app.test']), SessionFactors::of(SignInFactor::Passkey))];
    $browser->action('account.confirmWithPassword', ['currentPassword' => PASSWORD]);
    expect($browser->action('account.removePasskey', ['id' => $passkey])->status)->toBe(200)
        ->and($this->kernel->orm->connection()->select('SELECT COUNT(*) AS n FROM passkeys')[0]['n'])->toBe(0);
    work($this->kernel);
    $sent = $this->mail->sent(AccountMails::PASSKEY_REMOVED);
    expect($sent)->toHaveCount(1)
        ->and($sent[0]->isTo('ada@example.com'))->toBeTrue()
        ->and($sent[0]->props['passkey'])->toBe('Laptop');
});

it('turns two-factor on with a code, then asks for one at sign-in, each good once', function () {
    $browser = signUp($this->kernel, $this->mail, 'ada@example.com');
    $browser->action('account.confirmWithPassword', ['currentPassword' => PASSWORD]);
    $browser->action('account.beginTwoFactor');
    $browser->get('/account');
    $secret = Base32::decode((string) ($browser->page('twoFactorSetup')['secret'] ?? ''));
    expect($secret)->toBeString();

    // One code, kept: asked for again after a 30-second step has turned, a
    // code made afresh would be the next step's, which nothing has spent.
    $code = Totp::at((string) $secret, time());
    $confirmed = $browser->action('account.confirmTwoFactor', ['code' => $code]);
    expect($confirmed->status)->toBe(200);
    $codes = json_decode($confirmed->body(), true)['value']['recoveryCodes'] ?? [];
    expect($codes)->toHaveCount(10);

    $phone = new Browser($this->kernel, '192.0.2.80');
    expect($phone->action('account.signIn', ['email' => 'ada@example.com', 'password' => PASSWORD])->header('location'))->toStartWith('/sign-in/two-factor')
        ->and(signedInAs($phone))->toBeNull();
    $phone->get('/sign-in/two-factor');
    expect($phone->page('waiting'))->toBeTrue();

    // The code just used to confirm is spent; a recovery code works, once.
    expect($phone->action('account.completeSecondFactor', ['code' => $code])->status)->toBe(422);
    expect($phone->action('account.completeSecondFactor', ['code' => strtoupper($codes[0])])->status)->toBe(303)
        ->and(signedInAs($phone))->toBe('ada@example.com');

    $laptop = new Browser($this->kernel, '192.0.2.81');
    $laptop->action('account.signIn', ['email' => 'ada@example.com', 'password' => PASSWORD]);
    expect($laptop->action('account.completeSecondFactor', ['code' => $codes[0]])->status)->toBe(422);

    // A code from the app confirms a session, as the password does; a recovery code does not.
    expect($phone->action('account.confirmWithCode', ['code' => $codes[1]])->status)->toBe(422)
        ->and($phone->action('account.confirmWithCode', ['code' => Totp::at((string) $secret, time() + Totp::PERIOD)])->status)->toBe(200)
        ->and($phone->action('account.regenerateRecoveryCodes', ['currentPassword' => PASSWORD])->status)->toBe(200);
});

it('turns two-factor off with the password, signing out every other device, and tells the owner', function () {
    $browser = signUp($this->kernel, $this->mail, 'ada@example.com');
    $browser->action('account.confirmWithPassword', ['currentPassword' => PASSWORD]);
    $browser->action('account.beginTwoFactor');
    $browser->get('/account');
    $secret = (string) Base32::decode((string) ($browser->page('twoFactorSetup')['secret'] ?? ''));
    $codes = json_decode($browser->action('account.confirmTwoFactor', ['code' => Totp::at($secret, time())])->body(), true)['value']['recoveryCodes'] ?? [];
    $phone = new Browser($this->kernel, '192.0.2.85');
    $phone->action('account.signIn', ['email' => 'ada@example.com', 'password' => PASSWORD]);
    $phone->action('account.completeSecondFactor', ['code' => $codes[0]]);
    expect(signedInAs($phone))->toBe('ada@example.com');

    // The code proves a second factor on this session, as turning it off asks (ADR 0184).
    expect($browser->action('account.confirmWithCode', ['code' => Totp::at($secret, time() + Totp::PERIOD)])->status)->toBe(200);
    expect($browser->action('account.disableTwoFactor', ['currentPassword' => 'a guess'])->status)->toBe(422)
        ->and(signedInAs($phone))->toBe('ada@example.com');
    expect($browser->action('account.disableTwoFactor', ['currentPassword' => PASSWORD])->status)->toBe(200)
        ->and(signedInAs($browser))->toBe('ada@example.com')
        ->and(signedInAs($phone))->toBeNull();
    work($this->kernel);
    $this->mail->assertSentTo('ada@example.com', AccountMails::TWO_FACTOR_DISABLED);
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

it('lists, creates once and revokes API tokens, which outlive a sign-in elsewhere and end when every session does', function () {
    $browser = signUp($this->kernel, $this->mail, 'ada@example.com');
    $browser->get('/account/api-tokens');
    expect($browser->page('tokens'))->toBe([])
        ->and(array_column($browser->page('scopes'), 'value'))->toBe(['notes:read'])
        ->and($browser->page('confirmedUntil'))->toBeNull();

    // A signed-in session alone -- a stolen cookie -- makes no token: a
    // token outlives the session, so the owner confirms it is them first.
    $unconfirmed = $browser->action('account.createApiToken', ['name' => 'Export', 'scopes' => ['notes:read'], 'lifetime' => '30']);
    expect($unconfirmed->status)->toBe(422)
        ->and(json_decode($unconfirmed->body(), true)['errors'] ?? null)->toBe(['confirm' => [Accounts::CONFIRM_FIRST]])
        ->and($this->kernel->orm->connection()->select('SELECT id FROM api_tokens'))->toBe([]);
    expect($browser->action('account.confirmWithPassword', ['currentPassword' => PASSWORD])->status)->toBe(200);
    $browser->get('/account/api-tokens');
    expect($browser->page('confirmedUntil'))->toBeInt()->toBeGreaterThan(time());

    // A scope the API does not declare is refused, by its field; and a
    // token lasts a year at most, so none that never expires is offered.
    expect($browser->action('account.createApiToken', ['name' => 'Export', 'scopes' => ['notes:write'], 'lifetime' => '30'])->status)->toBe(422)
        ->and(array_column($browser->page('lifetimes'), 'value'))->toBe(['30', '90', '365'])
        ->and($browser->action('account.createApiToken', ['name' => 'Export', 'scopes' => ['notes:read'], 'lifetime' => 'never'])->status)->toBe(422);

    $created = $browser->action('account.createApiToken', ['name' => 'Export', 'scopes' => ['notes:read'], 'lifetime' => '30']);
    expect($created->status)->toBe(200);
    $token = (string) (json_decode($created->body(), true)['value']['token'] ?? '');
    expect($token)->toStartWith('tvr_');

    // The owner hears of it by email, by its name -- never its text.
    work($this->kernel);
    $told = $this->mail->sent(AccountMails::API_TOKEN_CREATED);
    expect($told)->toHaveCount(1)
        ->and($told[0]->isTo('ada@example.com'))->toBeTrue()
        ->and($told[0]->props['tokenName'])->toBe('Export')
        ->and($told[0]->props['url'])->toBe(ORIGIN . '/account/api-tokens')
        ->and($told[0]->props['secondaryUrl'])->toBe(ORIGIN . '/password/forgot')
        ->and(json_encode($told[0]->props))->not->toContain(substr($token, 37));

    // Listed by name, never its text.
    $browser->get('/account/api-tokens');
    expect(array_column($browser->page('tokens'), 'name'))->toBe(['Export'])
        ->and(json_encode($browser->props))->not->toContain($token);

    $notes = api($this->kernel, $token, 'recentNotes');
    expect($notes->status)->toBe(200, $notes->body());

    // Signing in on another device leaves it working; signing out everywhere ends it.
    $phone = new Browser($this->kernel, '192.0.2.70');
    $phone->action('account.signIn', ['email' => 'ada@example.com', 'password' => PASSWORD]);
    $phone->action('account.signOut');
    expect(api($this->kernel, $token, 'recentNotes')->status)->toBe(200);
    $browser->action('account.signOutEverywhere');
    expect(api($this->kernel, $token, 'recentNotes')->status)->toBe(401);

    // And revoking one ends it at once.
    $browser->action('account.signIn', ['email' => 'ada@example.com', 'password' => PASSWORD]);
    $browser->action('account.confirmWithPassword', ['currentPassword' => PASSWORD]);
    $second = (string) (json_decode($browser->action('account.createApiToken', ['name' => 'Second', 'scopes' => ['notes:read']])->body(), true)['value']['token'] ?? '');
    expect(api($this->kernel, $second, 'recentNotes')->status)->toBe(200);
    $browser->get('/account/api-tokens');
    $id = (string) ($browser->page('tokens')[0]['id'] ?? '');
    expect($browser->action('account.revokeApiToken', ['id' => $id])->status)->toBe(200)
        ->and(api($this->kernel, $second, 'recentNotes')->status)->toBe(401)
        ->and($browser->action('account.revokeApiToken', ['id' => $id])->status)->toBe(422);
});

it('sends a visitor to sign in from the API tokens page', function () {
    expect(new Browser($this->kernel)->get('/account/api-tokens')->header('location'))->toBe('/sign-in?next=%2Faccount%2Fapi-tokens');
});
