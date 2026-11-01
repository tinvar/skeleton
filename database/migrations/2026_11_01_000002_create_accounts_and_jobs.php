<?php

declare(strict_types=1);

use App\Database\Models\Account\User;
use Tinvar\Accounts\AccountsKit;
use Tinvar\Orm\Migrations\Migration;
use Tinvar\Orm\Schema\Schema;
use Tinvar\Orm\Schema\Table;
use Tinvar\Queue\DatabaseQueue;

/**
 * The accounts (ADR 0150) and the queue their email goes through.
 *
 * `users` is the app's own table, read through App\Auth\Account\Users;
 * everything else the accounts kit keeps -- sessions, auth epochs, emailed
 * links, passkeys and their challenges, two-factor secrets and recovery
 * codes -- AccountsKit::migrate() makes, each user column a foreign key to
 * `users`, so a user deleted takes all of it with them. The jobs and their
 * failures are the queue's.
 */
return new class extends Migration {
    public function up(Schema $schema): void
    {
        $schema->create('users', function (Table $table): void {
            $table->uuid('id')->primary();
            $table->string('email', 254);
            $table->string('name', 100);
            $table->string('password_hash');
            $table->timestamp('created_at');
            // One account per address, held by the database: two sign-ups
            // racing for one address cannot both make one.
            $table->unique(['email'], 'users_email');
        });

        AccountsKit::migrate($schema, User::class);
        DatabaseQueue::define($schema);
    }

    public function down(Schema $schema): void
    {
        $schema->drop('failed_jobs');
        $schema->drop('jobs');
        foreach (AccountsKit::TABLES as $table) {
            $schema->drop($table);
        }
        $schema->drop('users');
    }
};
