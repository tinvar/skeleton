<?php

declare(strict_types=1);

namespace App\Http\Data;

use Tinvar\Data\Data;

/**
 * A page that could not be shown, said plainly: what happened, why, the one
 * way forward, and a reference that finds the failure in the log.
 */
final readonly class ErrorPageData extends Data
{
    public function __construct(
        public int $status,
        public string $eyebrow,
        public string $title,
        public string $detail,
        public string $reference,
        public string $actionLabel,
        public string $actionHref,
    ) {}
}
