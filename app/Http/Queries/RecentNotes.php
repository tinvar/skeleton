<?php

declare(strict_types=1);

namespace App\Http\Queries;

use App\Http\Data\NoteData;
use App\Http\Data\NotesData;
use App\Database\Models\Note;
use Tinvar\Attributes\Authorize;
use Tinvar\Attributes\PublicAccess;
use Tinvar\Attributes\Query;
use Tinvar\Attributes\RequiresScope;
use Tinvar\Orm\Direction;
use Tinvar\Orm\Orm;

/**
 * The newest notes: what the home page's <NoteList> awaits.
 *
 * A server component calls it over the render's own socket, in the same PHP
 * request and as the same user, and codegen types it as `recentNotes()` in
 * the `@queries` module. Its #[Authorize] runs on every call, before its
 * body, and its body runs read-only: a write here is refused. Public, as
 * the action is: the notes are for everyone, signed in or not.
 *
 * And an API (ADR 0159): with #[RequiresScope] a script holding an API
 * token made on the account's API tokens page with `notes:read` calls it
 * at `POST /_api/{name}`, `Authorization: Bearer tvr_...`. Every scope a
 * token may hold is one declared like this.
 */
#[Query, PublicAccess('Anyone may read the notes, signed in or not.'), Authorize('viewAny', Note::class), RequiresScope('notes:read')]
final class RecentNotes
{
    /** The most one call may ask for. */
    public const int MAX = 50;

    public function __construct(private readonly Orm $orm) {}

    public function __invoke(int $limit = 10): NotesData
    {
        $notes = Note::query($this->orm)
            ->orderBy('createdAt', Direction::Desc)
            ->orderBy('id', Direction::Desc)
            ->limit(max(1, min($limit, self::MAX)))
            ->fetch();

        return new NotesData(
            array_map(static fn (Note $note): NoteData => new NoteData($note->id, $note->body, $note->createdAt), $notes),
            Note::query($this->orm)->count(),
        );
    }
}
