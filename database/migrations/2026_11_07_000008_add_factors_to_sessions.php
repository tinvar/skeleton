<?php

declare(strict_types=1);

use Tinvar\Accounts\Sessions;
use Tinvar\Orm\Migrations\Migration;
use Tinvar\Orm\Schema\Schema;

/**
 * What each session's sign-in proved (ADR 0184): a password, a passkey, a
 * password and a code. A database the accounts migration made before the
 * column was in the kit gains it here, every session in it reading as
 * having proved nothing -- signed in still, but no page that asks for two
 * factors opens to it until it proves them again. Every sign-in writes the
 * column, so it is made before the app serves; a new database already has it.
 */
return new class extends Migration {
    public function up(Schema $schema): void
    {
        Sessions::addFactors($schema);
    }

    public function down(Schema $schema): void
    {
        // The kit's sessions table has the column from its first migration on.
    }
};
