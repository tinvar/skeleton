<?php

declare(strict_types=1);

namespace App\Database\Models\Account;

use Carbon\CarbonImmutable;
use Tinvar\Accounts\AccountUser;
use Tinvar\Orm\Attributes\Column;
use Tinvar\Orm\Attributes\Id;
use Tinvar\Orm\Attributes\Table;
use Tinvar\Orm\Model;

/**
 * Someone with an account: an address they proved they read, a name, and a
 * password hash. The accounts kit reads it through AccountUser and changes
 * it through App\Auth\Account\Users, so add what the app needs beside these
 * -- a column, a relation -- and the kit carries on as it was.
 *
 * Every user's address was verified before the row existed: an account is
 * made only from a sign-up link that reached it (ADR 0150), so there is no
 * unverified state to keep.
 */
#[Table('users')]
final class User extends Model implements AccountUser
{
    #[Id] public private(set) string $id;

    /** Lowercased and trimmed (Emails::normalize()), and one account's alone. */
    #[Column] public string $email;
    #[Column] public string $name;
    #[Column] public string $passwordHash;

    #[Column] public CarbonImmutable $createdAt;

    public function authIdentifier(): string
    {
        return $this->id;
    }

    public function accountEmail(): string
    {
        return $this->email;
    }

    public function accountName(): string
    {
        return $this->name;
    }

    public function accountPasswordHash(): string
    {
        return $this->passwordHash;
    }
}
