<?php

declare(strict_types=1);

namespace App\Http\Data\Account;

use Tinvar\Data\Data;

/**
 * A token just issued, with its text: the answer to the action that asked
 * for it, shown once on the page that asked and kept nowhere -- not in the
 * page's props, a flash or the session.
 */
final readonly class IssuedApiTokenData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
        public string $token,
    ) {}
}
