<?php

declare(strict_types=1);

namespace App\Data;

use Tinvar\Data\Data;

/** One note, as the renderer is sent it. */
final readonly class NoteData extends Data
{
    public function __construct(
        public string $id,
        public string $body,
        public string $createdAt,
    ) {}
}
