<?php

declare(strict_types=1);

namespace App\Database\Seeders;

use App\Database\Models\Note;
use Tinvar\Orm\Connection;
use Tinvar\Orm\IdentityMap;
use Tinvar\Orm\Migrations\DatabaseSeeder;
use Tinvar\Orm\Orm;

/**
 * What `bin/tinvar db:seed` fills a new app's database with: one note, so
 * the home page has something on it. `tinvar new` runs it once; it adds
 * nothing to a database that has a note already.
 *
 * Never data the app needs in production: db:seed runs only where
 * TINVAR_ENV or --non-production says this is not production. Data
 * production needs is a migration's.
 */
final class WelcomeNote implements DatabaseSeeder
{
    public function options(): array
    {
        return [];
    }

    public function seed(Connection $connection, array $options, \Closure $out): void
    {
        $orm = new Orm($connection, new IdentityMap($connection->name()));
        if (Note::query($orm)->count() > 0) {
            $out('There are notes already; seeded nothing.');

            return;
        }

        $note = new Note();
        $note->body = 'Welcome. This note came from the seeder, App\Database\Seeders\WelcomeNote; add yours below.';
        $orm->save($note);
        $out('Seeded a welcome note.');
    }
}
