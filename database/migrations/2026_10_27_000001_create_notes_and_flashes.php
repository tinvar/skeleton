<?php

declare(strict_types=1);

use Tinvar\Orm\Migrations\Migration;
use Tinvar\Orm\Schema\Schema;
use Tinvar\Orm\Schema\Table;

/**
 * The notes, and the flashes a form posted without script leaves for the
 * page it is sent back to (App\Auth\Flashes).
 *
 * `php tinvar make:migration` writes the next one from the models, by
 * comparing them with the database: read it before you commit it.
 */
return new class extends Migration {
    public function up(Schema $schema): void
    {
        $schema->create('notes', function (Table $table): void {
            $table->uuid('id')->primary();
            $table->string('body', 500);
            // A moment, stored as the database keeps one (ADR 0145): on
            // SQLite ISO 8601 in UTC at one width, so it sorts as text.
            $table->timestamp('created_at');
            $table->index(['created_at']);
        });

        // By a hash of the session id, so the table is not a list of cookies.
        $schema->create('flashes', function (Table $table): void {
            $table->string('session')->primary();
            $table->text('data');
            $table->integer('expires_at');
        });
    }

    public function down(Schema $schema): void
    {
        $schema->drop('flashes');
        $schema->drop('notes');
    }
};
