<?php

declare(strict_types=1);

/**
 * The admin panel and its notes (App\Http\Resources\NoteResource), through
 * the whole PHP request path: the panel's guard, then each screen's policy,
 * against a database of each test's own. APP_ADMINS names the one admin;
 * outside `php tinvar serve --dev`, an unset list lets nobody in.
 */

namespace Tests\Feature;

use App\Auth\Admin\Admins;
use App\Database\Migrations;
use App\Database\Models\Account\User;
use App\Database\Models\Note;
use App\Kernel;
use Tinvar\Http\Request;
use Tinvar\Http\Response;
use Tinvar\Observability\Logger;
use Tinvar\Observability\MemorySink;
use Tinvar\Observability\Telemetry;
use Tinvar\Testing\Testing;

use function Tinvar\Testing\actingAs;
use function Tinvar\Testing\get;

/** A Kernel with its own database, APP_ADMINS set to $admins for it. */
function adminApp(?string $admins): Kernel
{
    putenv($admins === null ? 'APP_ADMINS' : "APP_ADMINS={$admins}");
    $kernel = Kernel::fromEnvironment(database: ':memory:', telemetry: new Telemetry(new Logger(new MemorySink())));
    Migrations::migrate($kernel->orm->connection());
    Testing::use($kernel);

    return $kernel;
}

function account(Kernel $kernel, string $email): User
{
    $user = new User();
    $user->email = $email;
    $user->name = 'Someone';
    $user->passwordHash = password_hash('a long enough password', PASSWORD_DEFAULT);
    $kernel->orm->save($user);

    return $user;
}

function note(Kernel $kernel, string $body): Note
{
    $note = new Note();
    $note->body = $body;
    $kernel->orm->save($note);

    return $note;
}

afterEach(function () {
    Testing::forget();
    putenv('APP_ADMINS');
});

it('sends a visitor to sign in, and brings them back', function () {
    adminApp('ada@example.com');

    get('/admin/notes')->assertRedirect('/sign-in?next=%2Fadmin%2Fnotes');
});

it('refuses an account APP_ADMINS does not name, and one when it names nobody', function () {
    $kernel = adminApp('ada@example.com');
    actingAs(account($kernel, 'grace@example.com'))->get('/admin/notes')->assertForbidden();

    Testing::forget();
    $kernel = adminApp(null);
    actingAs(account($kernel, 'ada@example.com'))->get('/admin/notes')->assertForbidden();
});

it('lists, changes and deletes notes for an admin, however the address is spelled', function () {
    $kernel = adminApp(' Ada@Example.com ,grace@example.com');
    $ada = account($kernel, 'ada@example.com');
    $note = note($kernel, 'A note to keep.');
    note($kernel, 'A note to delete.');

    actingAs($ada)->get('/admin/notes')->assertOk();
    actingAs($ada)->get('/admin/notes?search=keep')->assertOk();
    actingAs($ada)->get("/admin/notes/{$note->id}/edit")->assertOk();

    actingAs($ada)->action('NoteResource.update', record: $note->id, values: ['body' => 'A note, changed.'])->assertRedirect();
    expect($kernel->orm->find(Note::class, $note->id)?->body)->toBe('A note, changed.');

    actingAs($ada)->action('NoteResource.update', record: $note->id, values: ['body' => str_repeat('a', 501)])->assertInvalid(['values.body']);

    actingAs($ada)->action('NoteResource.destroy', record: $note->id)->assertRedirect('/admin/notes');
    expect(Note::query($kernel->orm)->count())->toBe(1);
});

it('holds a note\'s changes to the admins wherever they are asked', function () {
    $kernel = adminApp('ada@example.com');
    $note = note($kernel, 'Hands off.');

    actingAs(account($kernel, 'grace@example.com'))->action('NoteResource.destroy', record: $note->id)->assertForbidden();
    expect(Note::query($kernel->orm)->count())->toBe(1);
});

it('lets every signed-in account in under `tinvar serve --dev` while APP_ADMINS is unset, and never a guest', function () {
    $user = new User();
    $user->email = 'anyone@example.com';

    $here = static fn (): bool => true;

    expect(new Admins([], openInDev: true, reachedLocally: $here)->allows($user))->toBeTrue()
        ->and(new Admins([], openInDev: true, reachedLocally: $here)->allows(null))->toBeFalse()
        ->and(new Admins([], reachedLocally: $here)->allows($user))->toBeFalse()
        ->and(Admins::fromList('ada@example.com', openInDev: true, reachedLocally: $here)->allows($user))->toBeFalse()
        // Open in dev, and asked of no request it can tell came from here: shut.
        ->and(new Admins([], openInDev: true)->allows($user))->toBeFalse()
        ->and(new Admins([], openInDev: true, reachedLocally: static fn (): bool => false)->allows($user))->toBeFalse();
});

// ADR 0219: dev mode shared through a tunnel (--tunnel, --public-host) is
// reached from beyond this machine, so an unset APP_ADMINS lets nobody in.
it('shuts the panel under `tinvar serve --dev` once a tunnel shares it, while APP_ADMINS is unset', function () {
    $user = new User();
    $user->email = 'anyone@example.com';

    $here = static fn (): bool => true;

    expect(Admins::forServing(null, dev: true, env: [], reachedLocally: $here)->allows($user))->toBeTrue()
        ->and(Admins::forServing(null, dev: true, env: ['TINVAR_DEV_PUBLIC_HOST' => 'grade-habits.trycloudflare.com'], reachedLocally: $here)->allows($user))->toBeFalse()
        ->and(Admins::forServing(null, dev: false, env: [], reachedLocally: $here)->allows($user))->toBeFalse()
        ->and(Admins::forServing('anyone@example.com', dev: true, env: ['TINVAR_DEV_PUBLIC_HOST' => 'dev.example.com'])->allows($user))->toBeTrue();
});

// A tunnel started by hand -- `ngrok http`, `cloudflared tunnel --url` --
// sets no TINVAR_DEV_PUBLIC_HOST, and its requests arrive from loopback
// too. So dev opens the unnamed panel only to a request that names this
// machine and carries no proxy's forwarding headers; anything else needs
// APP_ADMINS (ADR 0219).
it('tells a request from this machine apart from one a tunnel brought', function (array $headers, string $peer, bool $local) {
    expect(Admins::reachedLocally(new Request('GET', '/admin/notes', $headers, remoteAddress: $peer)))->toBe($local);
})->with([
    'localhost' => [['host' => 'localhost:8080'], '127.0.0.1', true],
    'a name under localhost' => [['host' => 'admin.localhost:8080'], '::1', true],
    '127.0.0.1' => [['host' => '127.0.0.1:6123'], '127.0.0.1', true],
    'a tunnel\'s own name' => [['host' => 'grade-habits.trycloudflare.com'], '127.0.0.1', false],
    'ngrok rewriting the host' => [['host' => 'localhost:8080', 'x-forwarded-for' => '203.0.113.9', 'x-forwarded-host' => 'abc.ngrok-free.app'], '127.0.0.1', false],
    'cloudflared told the host' => [['host' => 'localhost:8080', 'cf-connecting-ip' => '203.0.113.9'], '127.0.0.1', false],
    'Forwarded' => [['host' => 'localhost:8080', 'forwarded' => 'for=203.0.113.9'], '127.0.0.1', false],
    'X-Real-IP' => [['host' => 'localhost:8080', 'x-real-ip' => '203.0.113.9'], '127.0.0.1', false],
    'X-Forwarded-Proto' => [['host' => 'localhost:8080', 'x-forwarded-proto' => 'https'], '127.0.0.1', false],
    'another machine' => [['host' => 'localhost:8080'], '192.168.1.20', false],
    'no Host' => [[], '127.0.0.1', false],
]);

it('opens the unnamed panel under `tinvar serve --dev` only to a request from this machine', function (array $headers, int $status) {
    putenv('APP_ADMINS');
    $kernel = Kernel::fromEnvironment(database: ':memory:', dev: true, telemetry: new Telemetry(new Logger(new MemorySink())));
    Migrations::migrate($kernel->orm->connection());
    $user = account($kernel, 'anyone@example.com');
    $cookies = $kernel->signedIn($user, new Request('GET', '/', $headers, remoteAddress: '127.0.0.1'));

    $response = $kernel->handleWith(
        new Request('GET', '/admin/notes', $headers, cookies: $cookies, remoteAddress: '127.0.0.1'),
        static fn (): Response => Response::make(200, 'the panel'),
    );

    expect($response->status)->toBe($status);
})->with([
    'from this machine' => [['host' => 'localhost:8080'], 200],
    'through a tunnel by its own name' => [['host' => 'grade-habits.trycloudflare.com', 'x-forwarded-for' => '203.0.113.9'], 403],
    'through a tunnel rewriting the host' => [['host' => 'localhost:8080', 'x-forwarded-for' => '203.0.113.9'], 403],
]);
