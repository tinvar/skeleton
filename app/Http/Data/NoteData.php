<?php

declare(strict_types=1);

namespace App\Http\Data;

use Carbon\CarbonImmutable;
use Tinvar\Data\Data;

/** One note, as the renderer is sent it: createdAt as ISO 8601 in UTC, to the millisecond. */
final readonly class NoteData extends Data
{
    public function __construct(
        public string $id,
        public string $body,
        public CarbonImmutable $createdAt,
    ) {}
}
