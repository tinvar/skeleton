<?php

declare(strict_types=1);

namespace App\Http\Data;

use Tinvar\Data\Data;

/** What the home page's loader hands its view: the page's name, and how many notes it lists. */
final readonly class HomePageData extends Data
{
    public function __construct(
        public string $appName,
        public int $limit,
    ) {}
}
