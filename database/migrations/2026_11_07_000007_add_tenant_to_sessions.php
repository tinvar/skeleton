<?php

declare(strict_types=1);

use Tinvar\Accounts\Sessions;
use Tinvar\Orm\Migrations\Migration;
use Tinvar\Orm\Schema\Schema;

/**
 * The tenant each session runs as (ADR 0181), for an app that serves many
 * tenants on one host and lets its users switch between them. A database
 * the accounts migration made before the column was in the kit gains it
 * here, every session in it running as no tenant until its user switches;
 * a new one already has it.
 */
return new class extends Migration {
    public function up(Schema $schema): void
    {
        Sessions::addTenant($schema);
    }

    public function down(Schema $schema): void
    {
        // The kit's sessions table has the column from its first migration on.
    }
};
