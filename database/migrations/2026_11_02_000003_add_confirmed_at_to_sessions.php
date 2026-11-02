<?php

declare(strict_types=1);

use Tinvar\Accounts\Sessions;
use Tinvar\Orm\Migrations\Migration;
use Tinvar\Orm\Schema\Schema;

/**
 * When a session's owner last proved it was them (ADR 0153): adding a
 * passkey and turning two-factor on need it within five minutes. A
 * database the accounts migration made before the column was in the kit
 * gains it here; a new one already has it.
 */
return new class extends Migration {
    public function up(Schema $schema): void
    {
        Sessions::addConfirmedAt($schema);
    }

    public function down(Schema $schema): void
    {
        // The kit's sessions table has the column from its first migration on.
    }
};
