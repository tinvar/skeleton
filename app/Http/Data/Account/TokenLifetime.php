<?php

declare(strict_types=1);

namespace App\Http\Data\Account;

use Carbon\CarbonImmutable;

/**
 * How long a new API token lasts, as the API tokens page offers it: thirty
 * days unless the user chooses otherwise, and a year at most -- the longest
 * the Kernel's ApiAccess lets one last (ApiTokens::MAX_LIFETIME_DAYS). None
 * never expires: a token outlives every session, so one that never ended
 * would be a lasting way in for whoever made it.
 */
enum TokenLifetime: string
{
    case ThirtyDays = '30';
    case NinetyDays = '90';
    case OneYear = '365';

    /** When a token issued at $now with this lifetime stops working. */
    public function expiresAt(CarbonImmutable $now): CarbonImmutable
    {
        return $now->addDays((int) $this->value);
    }

    /** What the page's choice says. */
    public function label(): string
    {
        return match ($this) {
            self::ThirtyDays => '30 days',
            self::NinetyDays => '90 days',
            self::OneYear => 'A year',
        };
    }
}
