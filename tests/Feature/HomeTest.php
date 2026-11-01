<?php

declare(strict_types=1);

/**
 * The home page, its query and its action, through the whole PHP request
 * path -- routing, the CSRF check, authorization, validation, loaders --
 * against a database of each test's own, without the renderer: get()
 * records what PHP would have sent it.
 */

namespace Tests\Feature;

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
use function Tinvar\Testing\get;
use function Tinvar\Testing\query;

beforeEach(function () {
    $kernel = Kernel::fromEnvironment(database: ':memory:', telemetry: new Telemetry(new Logger(new MemorySink())));
    Migrations::migrate($kernel->orm->connection());
    Testing::use($kernel);
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

it('refuses an empty note and one too long, and saves neither', function () {
    action([NoteActions::class, 'add'], body: '')->assertInvalid(['body']);
    action([NoteActions::class, 'add'], body: '   ')->assertInvalid(['body']);
    action([NoteActions::class, 'add'], body: str_repeat('a', 501))->assertInvalid(['body']);

    query(RecentNotes::class)->assertData(fn (NotesData $data): bool => $data->total === 0);
});

it('answers an address with no page with the error page', function () {
    get('/nowhere')->assertNotFound()->assertErrorPage(404, ErrorPage::class);
});
