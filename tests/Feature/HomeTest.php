<?php

declare(strict_types=1);

/**
 * The home page, its query and its action, through the whole PHP request
 * path -- routing, the CSRF check, authorization, validation, loaders --
 * against a database of each test's own, without the renderer: get()
 * records what PHP would have sent it.
 */

namespace Tests\Feature;

use App\Database\Models\Account\User;
use App\Http\Actions\NoteActions;
use App\Http\Data\NotesData;
use App\Database\Migrations;
use App\Kernel;
use App\Http\Pages\ErrorPage;
use App\Http\Pages\Home;
use App\Http\Queries\RecentNotes;
use Tinvar\Observability\Logger;
use Tinvar\Observability\MemorySink;
use Tinvar\Observability\Telemetry;
use Tinvar\Testing\AssertablePage;
use Tinvar\Testing\Testing;

use function Tinvar\Testing\action;
use function Tinvar\Testing\actingAs;
use function Tinvar\Testing\get;
use function Tinvar\Testing\query;

beforeEach(function () {
    $kernel = Kernel::fromEnvironment(database: ':memory:', telemetry: new Telemetry(new Logger(new MemorySink())));
    Migrations::migrate($kernel->orm->connection());
    Testing::use($kernel);
    $this->kernel = $kernel;
});

afterEach(function () {
    Testing::forget();
});

it('shows the home page to anyone', function () {
    get('/')
        ->assertOk()
        ->assertPage(Home::class, fn (AssertablePage $page) => $page->where('appName', Kernel::NAME)->where('limit', 20));
});

it('adds a note, and lists it newest first', function () {
    action([NoteActions::class, 'add'], body: 'The first note.')->assertRevalidated('home');
    action([NoteActions::class, 'add'], body: '  The second note.  ')->assertRevalidated('home');

    query(RecentNotes::class, limit: 10)->assertData(
        fn (NotesData $data): bool => $data->total === 2 && $data->notes[0]->body === 'The second note.' && $data->notes[1]->body === 'The first note.',
    );
});

// Leaving a note is public by choice, so a visitor's are budgeted: ten an
// hour from one address, past the actions' throttle every caller spends.
// An account's are not: it is throttled by the account (ADR 0223).
it('holds a visitor to ten notes an hour from one address, and not someone signed in', function () {
    for ($i = 1; $i <= 10; $i++) {
        action([NoteActions::class, 'add'], body: "Note {$i}.")->assertRevalidated('home');
    }
    action([NoteActions::class, 'add'], body: 'One too many.')->assertStatus(429);

    $user = new User();
    $user->email = 'ada@example.com';
    $user->name = 'Ada';
    $user->passwordHash = password_hash('a long enough password', PASSWORD_DEFAULT);
    $this->kernel->orm->save($user);
    actingAs($user)->action([NoteActions::class, 'add'], body: 'Signed in.')->assertRevalidated('home');

    query(RecentNotes::class, limit: 20)->assertData(
        fn (NotesData $data): bool => $data->total === 11 && $data->notes[0]->body === 'Signed in.',
    );
});

it('refuses an empty note and one too long, and saves neither', function () {
    action([NoteActions::class, 'add'], body: '')->assertInvalid(['body']);
    action([NoteActions::class, 'add'], body: '   ')->assertInvalid(['body']);
    action([NoteActions::class, 'add'], body: str_repeat('a', 501))->assertInvalid(['body']);

    query(RecentNotes::class)->assertData(fn (NotesData $data): bool => $data->total === 0);
});

it('answers an address with no page with the error page', function () {
    get('/nowhere')->assertNotFound()->assertErrorPage(404, ErrorPage::class);
});
