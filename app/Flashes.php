<?php

declare(strict_types=1);

namespace App;

use Tinvar\Http\Flash;
use Tinvar\Http\FlashStore;
use Tinvar\Http\Request;
use Tinvar\Orm\Orm;

/**
 * What a form posted without script leaves for the page it is sent back to
 * -- its errors and its input -- in the `flashes` table, by session.
 *
 * Keyed by a hash of the session id, so a copy of the table is not a list
 * of cookies; one row per session, replaced by the next flash; and taken
 * with DELETE ... RETURNING, so two requests cannot both read it. Only a
 * request whose cookie says a flash is waiting reaches pull() (Flash::COOKIE),
 * so a page with nothing flashed costs no statement.
 */
final class Flashes implements FlashStore
{
    /** Expired rows deleted per flash written, at most. */
    public const int SWEEP_BATCH = 100;

    public function __construct(
        private readonly Orm $orm,
        #[\SensitiveParameter]
        private readonly string $key,
    ) {}

    public function put(Request $request, array $flash): void
    {
        $session = $this->sessionOf($request);
        if ($session === null) {
            return;
        }

        $now = time();
        $connection = $this->orm->connection();
        $connection->transaction(static function () use ($connection, $session, $flash, $now): void {
            $connection->execute(
                'INSERT INTO flashes (session, data, expires_at) VALUES (?, ?, ?) '
                . 'ON CONFLICT (session) DO UPDATE SET data = excluded.data, expires_at = excluded.expires_at',
                [$session, json_encode($flash, JSON_THROW_ON_ERROR), $now + Flash::LIFETIME],
            );

            // Rows come only from flashes, one each, so sweeping a batch per
            // flash keeps the table to about one lifetime's worth.
            $connection->execute(
                'DELETE FROM flashes WHERE session IN (SELECT session FROM flashes WHERE expires_at <= ? LIMIT '
                . self::SWEEP_BATCH . ')',
                [$now],
            );
        });
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
        if ($connection->select('SELECT 1 FROM flashes WHERE session = ?', [$session]) === []) {
            return [];
        }

        $row = $connection->select('DELETE FROM flashes WHERE session = ? RETURNING data, expires_at', [$session])[0] ?? null;
        if ($row === null || (int) $row['expires_at'] <= time()) {
            return [];
        }

        $flash = json_decode((string) $row['data'], true);

        /** @var array<string, mixed> */
        return \is_array($flash) ? $flash : [];
    }

    /** A hash of the request's session id, or null without a valid one. */
    private function sessionOf(Request $request): ?string
    {
        $id = Session::id($request->cookie(Session::COOKIE), $this->key);

        return $id === null ? null : hash('sha256', $id);
    }
}
