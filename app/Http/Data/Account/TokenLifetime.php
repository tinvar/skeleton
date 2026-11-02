<?php

declare(strict_types=1);

namespace App\Http\Data\Account;

use Carbon\CarbonImmutable;

/**
 * How long a new API token lasts, as the API tokens page offers it: thirty
 * days unless the user chooses otherwise, and never only when they say so.
 */
enum TokenLifetime: string
{
    case ThirtyDays = '30';
    case NinetyDays = '90';
    case OneYear = '365';
    case Never = 'never';

    /** When a token issued at $now with this lifetime stops working; null for never. */
    public function expiresAt(CarbonImmutable $now): ?CarbonImmutable
    {
        return $this === self::Never ? null : $now->addDays((int) $this->value);
    }

    /** What the page's choice says. */
    public function label(): string
    {
        return match ($this) {
            self::ThirtyDays => '30 days',
            self::NinetyDays => '90 days',
            self::OneYear => 'A year',
            self::Never => 'No expiry',
        };
    }
}
