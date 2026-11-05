<?php

declare(strict_types=1);

use Tinvar\Accounts\Sessions;
use Tinvar\Orm\Migrations\Migration;
use Tinvar\Orm\Schema\Schema;

/**
 * What each session's sign-in was checked against (ADR 0165): the account's
 * credential epoch and password hash, sealed, so a revocation that commits
 * while a sign-in is under way ends the session it makes. A database the
 * accounts migration made before the column was in the kit gains it here,
 * and each session in it signs in once more; a new one already has it.
 */
return new class extends Migration {
    public function up(Schema $schema): void
    {
        Sessions::addCredentials($schema);
    }

    public function down(Schema $schema): void
    {
        // The kit's sessions table has the column from its first migration on.
    }
};
