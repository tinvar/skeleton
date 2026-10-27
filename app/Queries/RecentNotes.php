<?php

declare(strict_types=1);

namespace App\Queries;

use App\Data\NoteData;
use App\Data\NotesData;
use App\Models\Note;
use Tinvar\Attributes\Authorize;
use Tinvar\Attributes\PublicAccess;
use Tinvar\Attributes\Query;
use Tinvar\Orm\Direction;
use Tinvar\Orm\Orm;

/**
 * The newest notes: what the home page's <NoteList> awaits.
 *
 * A server component calls it over the render's own socket, in the same PHP
 * request and as the same user, and codegen types it as `recentNotes()` in
 * the `@queries` module. Its #[Authorize] runs on every call, before its
 * body, and its body runs read-only: a write here is refused. Public, as
 * the action is, while the app has no accounts.
 */
#[Query, PublicAccess('Anyone may read the notes: the app has no accounts yet.'), Authorize('viewAny', Note::class)]
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
