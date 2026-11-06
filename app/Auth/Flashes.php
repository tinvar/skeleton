<?php

declare(strict_types=1);

namespace App\Auth;

use Tinvar\Accounts\SessionCookie;
use Tinvar\Http\Flash;
use Tinvar\Http\FlashStore;
use Tinvar\Http\Request;
use Tinvar\Orm\NamesDriver;
use Tinvar\Orm\Orm;

/**
 * What a form posted without script leaves for the page it is sent back to
 * -- its errors and its input -- in the `flashes` table, by session.
 *
 * Keyed by a hash of the session id, so a copy of the table is not a list
 * of cookies; one row per session, replaced by the next flash; and taken by
 * the request whose DELETE removes the row it read, so two requests cannot
 * both read it. Only a request whose cookie says a flash is waiting reaches
 * pull() (Flash::COOKIE), so a page with nothing flashed costs no statement.
 *
 * Every statement is one SQLite, Postgres and MySQL all read (ADR 0169), but
 * for the upsert, which none of them spells as another does.
 */
final class Flashes implements FlashStore
{
    /** Expired rows deleted per flash written, at most. */
    public const int SWEEP_BATCH = 100;

    public function __construct(
        private readonly Orm $orm,
        private readonly SessionCookie $cookie,
    ) {}

    public function put(Request $request, array $flash): void
    {
        $session = $this->sessionOf($request);
        if ($session === null) {
            return;
        }

        $now = time();
        $connection = $this->orm->connection();
        $connection->execute(
            $connection instanceof NamesDriver && $connection->driver() === 'mysql'
                ? 'INSERT INTO flashes (session, data, expires_at) VALUES (?, ?, ?) '
                    . 'ON DUPLICATE KEY UPDATE data = VALUES(data), expires_at = VALUES(expires_at)'
                : 'INSERT INTO flashes (session, data, expires_at) VALUES (?, ?, ?) '
                    . 'ON CONFLICT (session) DO UPDATE SET data = excluded.data, expires_at = excluded.expires_at',
            [$session, json_encode($flash, JSON_THROW_ON_ERROR), $now + Flash::LIFETIME],
        );

        // Rows come only from flashes, one each, so sweeping a batch per
        // flash keeps the table to about one lifetime's worth. Read, then
        // deleted by key where still expired: MySQL takes no LIMIT in a
        // subquery, nor a subquery of the table it deletes from. And after
        // the flash is written, not in its transaction, so no put holds its
        // own row while it waits for another's.
        $expired = array_column($connection->select('SELECT session FROM flashes WHERE expires_at <= ? LIMIT ' . self::SWEEP_BATCH, [$now]), 'session');
        if ($expired !== []) {
            $connection->execute(
                'DELETE FROM flashes WHERE expires_at <= ? AND session IN (' . implode(', ', array_fill(0, \count($expired), '?')) . ')',
                [$now, ...$expired],
            );
        }
    }

    public function pull(Request $request): array
    {
        $session = $this->sessionOf($request);
        if ($session === null) {
            return [];
        }

        // Read first: the cookie is presence alone, which anyone can send,
        // and a DELETE takes SQLite's write lock whether or not a row is
        // there. A page view with nothing waiting writes nothing.
        $connection = $this->orm->connection();
        $row = $connection->select('SELECT data, expires_at FROM flashes WHERE session = ?', [$session])[0] ?? null;
        if ($row === null) {
            return [];
        }

        // Then taken, where it is still the row read: of two requests one
        // gets it, and a flash put in its place meanwhile waits for the next
        // page. DELETE ... RETURNING did both at once, and is not MySQL's.
        $taken = $connection->execute(
            'DELETE FROM flashes WHERE session = ? AND expires_at = ? AND data = ?',
            [$session, $row['expires_at'], $row['data']],
        );
        if ($taken !== 1 || (int) $row['expires_at'] <= time()) {
            return [];
        }

        $flash = json_decode((string) $row['data'], true);

        /** @var array<string, mixed> */
        return \is_array($flash) ? $flash : [];
    }

    /** A hash of the request's session id, or null without a valid one. */
    private function sessionOf(Request $request): ?string
    {
        $id = $this->cookie->id($request->cookie($this->cookie->name));

        return $id === null ? null : hash('sha256', $id);
    }
}
