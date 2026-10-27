<?php

declare(strict_types=1);

namespace App\Database;

use Tinvar\Orm\Connection;
use Tinvar\Orm\Migrations\Migrator;

/**
 * The app's migrations, in migrations/, one file each: `bin/tinvar migrate`
 * runs them, bin/serve runs them on every start, and so does every test
 * that builds a database.
 */
final class Migrations
{
    public const string PATH = __DIR__ . '/migrations';

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
