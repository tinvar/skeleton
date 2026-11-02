<?php

declare(strict_types=1);

namespace App\Auth\Account;

use App\Database\Models\Account\User;
use Tinvar\Accounts\AccountUser;
use Tinvar\Accounts\AccountUsers;
use Tinvar\Accounts\EmailTaken;
use Tinvar\Orm\Orm;

/**
 * The app's users, as the accounts kit reads and changes them (ADR 0151):
 * the one class that knows the kit's accounts are App\Database\Models\
 * Account\User rows. An app that keeps its people elsewhere -- another
 * table, another key -- changes this and the model, and nothing else.
 *
 * Every address arrives normalized, and the `users` table keeps one account
 * per address with a unique index, which is what makes create() and
 * changeEmail() refuse a taken one even when two requests race.
 */
final readonly class Users implements AccountUsers
{
    public function __construct(private Orm $orm) {}

    public function find(string $id): ?AccountUser
    {
        $user = $this->orm->find(User::class, $id);

        return $user instanceof User ? $user : null;
    }

    public function findByEmail(string $email): ?AccountUser
    {
        return $this->orm->whereIn(User::class, 'email', [$email])[0] ?? null;
    }

    public function create(string $email, string $name, string $passwordHash): AccountUser
    {
        if ($this->findByEmail($email) !== null) {
            throw new EmailTaken();
        }

        $user = new User();
        $user->email = $email;
        $user->name = $name;
        $user->passwordHash = $passwordHash;
        // Its createdAt and id are the ORM's, stamped as it is inserted.
        $this->save($user);

        return $user;
    }

    public function changePassword(AccountUser $user, string $passwordHash): void
    {
        $user = $this->own($user);
        $user->passwordHash = $passwordHash;
        $this->orm->save($user);
    }

    public function changeEmail(AccountUser $user, string $email): void
    {
        $taken = $this->findByEmail($email);
        if ($taken !== null && $taken->authIdentifier() !== $user->authIdentifier()) {
            throw new EmailTaken();
        }
        $user = $this->own($user);
        $user->email = $email;
        $this->save($user);
    }

    public function passwordHashes(int $limit): array
    {
        $rows = $this->orm->connection()->select('SELECT password_hash FROM users ORDER BY random() LIMIT ' . $limit);

        return array_values(array_filter(array_map(static fn (array $row): mixed => $row['password_hash'] ?? null, $rows), \is_string(...)));
    }

    /** Saved, a unique index's refusal read as the address being taken: two requests raced for it. */
    private function save(User $user): void
    {
        try {
            $this->orm->save($user);
        } catch (\PDOException $e) {
            if (str_starts_with((string) $e->getCode(), '23')) {
                throw new EmailTaken(previous: $e);
            }

            throw $e;
        }
    }

    private function own(AccountUser $user): User
    {
        return $user instanceof User ? $user : throw new \InvalidArgumentException('Users changes App\'s own users, and was handed ' . get_debug_type($user) . '.');
    }
}
