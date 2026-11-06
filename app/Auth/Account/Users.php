<?php

declare(strict_types=1);

namespace App\Auth\Account;

use App\Database\Models\Account\User;
use Random\Randomizer;
use Tinvar\Accounts\AccountUser;
use Tinvar\Accounts\AccountUsers;
use Tinvar\Accounts\EmailTaken;
use Tinvar\Orm\Orm;

/**
 * The app's users, as the accounts kit reads and changes them (ADR 0153):
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
    /** What a hash's digest is written in: bcrypt's base64 and Argon2's, one character apart. */
    private const string DIGEST = './+0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';

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

    public function create(string $email, string $name, #[\SensitiveParameter] string $passwordHash): AccountUser
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

    public function changePassword(AccountUser $user, #[\SensitiveParameter] string $passwordHash): void
    {
        $user = $this->own($user);
        $user->passwordHash = $passwordHash;
        $this->orm->save($user);
    }

    /**
     * One conditional write, its row locked while it is compared (ADR 0168):
     * a hash stored since $current was read -- a reset's, a change's,
     * another sign-in's rehash -- stays.
     *
     * update() writes no row past a model's #[BeforeSave] or #[AfterSave]
     * methods, and refuses User once it has any: then this is
     * `UPDATE users SET password_hash = ? WHERE id = ? AND password_hash = ?`
     * through $this->orm->connection() -- never a read and a save(), which
     * writes over whatever is stored.
     */
    public function changePasswordFrom(AccountUser $user, #[\SensitiveParameter] string $current, #[\SensitiveParameter] string $passwordHash): bool
    {
        return $this->orm->query(User::class)
            ->where('id', $this->own($user)->id)
            ->where('passwordHash', $current)
            ->update(['passwordHash' => $passwordHash]) === 1;
    }

    /** Read from the table, past the identity map, which holds the user as the request first read them. */
    public function storedPasswordHash(AccountUser $user): ?string
    {
        $hash = $this->orm->query(User::class)->where('id', $user->authIdentifier())->pluck('passwordHash')[0] ?? null;

        return \is_string($hash) ? $hash : null;
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

    /**
     * From a point drawn at random among the hashes' digests -- the last ten
     * characters of each, which say nothing of the algorithm or the cost a
     * hash was made with -- and on around to it: a sample favouring no way
     * of hashing, drawn afresh each time as random() drew it, so nobody can
     * make hashes of their own the ones always read. SQLite, Postgres and
     * MySQL all read this; MySQL spells random() RAND() (ADR 0169).
     */
    public function passwordHashes(int $limit): array
    {
        $digest = 'SUBSTR(password_hash, LENGTH(password_hash) - 9)';
        $rows = $this->orm->connection()->select(
            "SELECT password_hash FROM users ORDER BY CASE WHEN {$digest} >= ? THEN 0 ELSE 1 END, {$digest} LIMIT {$limit}",
            [new Randomizer()->getBytesFromString(self::DIGEST, 10)],
        );

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
