<?php

declare(strict_types=1);

namespace App\Database;

use Tinvar\Orm\Connection;
use Tinvar\Orm\Migrations\Migrator;

/**
 * The app's migrations, in database/migrations/ beside app/, one file each:
 * `php tinvar migrate` runs them, `tinvar serve` runs them on every start, and so
 * does every test that builds a database. Each returns a class of its own
 * that nothing names, so they stay out of the app's namespace; this class,
 * which says where they are, is the database's under app/Database.
 */
final class Migrations
{
    public const string PATH = __DIR__ . '/../../database/migrations';

    /**
     * Brings a database up to date: a new one gets the whole schema, an
     * older one what it lacks.
     *
     * @return list<string> the migrations run
     */
    public static function migrate(Connection $connection): array
    {
        return new Migrator($connection, self::PATH)->migrate();
    }
}
