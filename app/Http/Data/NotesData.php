<?php

declare(strict_types=1);

namespace App\Http\Data;

use Tinvar\Data\Data;

/** The newest notes, newest first, and how many there are in all. */
final readonly class NotesData extends Data
{
    /** @param list<NoteData> $notes */
    public function __construct(
        public array $notes,
        public int $total,
    ) {}
}
