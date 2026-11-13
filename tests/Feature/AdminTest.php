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

    expect(new Admins([], openInDev: true)->allows($user))->toBeTrue()
        ->and(new Admins([], openInDev: true)->allows(null))->toBeFalse()
        ->and(new Admins([])->allows($user))->toBeFalse()
        ->and(Admins::fromList('ada@example.com', openInDev: true)->allows($user))->toBeFalse();
});

// ADR 0219: dev mode shared through a tunnel (--tunnel, --public-host) is
// reached from beyond this machine, so an unset APP_ADMINS lets nobody in.
it('shuts the panel under `tinvar serve --dev` once a tunnel shares it, while APP_ADMINS is unset', function () {
    $user = new User();
    $user->email = 'anyone@example.com';

    expect(Admins::forServing(null, dev: true, env: [])->allows($user))->toBeTrue()
        ->and(Admins::forServing(null, dev: true, env: ['TINVAR_DEV_PUBLIC_HOST' => 'grade-habits.trycloudflare.com'])->allows($user))->toBeFalse()
        ->and(Admins::forServing(null, dev: false, env: [])->allows($user))->toBeFalse()
        ->and(Admins::forServing('anyone@example.com', dev: true, env: ['TINVAR_DEV_PUBLIC_HOST' => 'dev.example.com'])->allows($user))->toBeTrue();
});
