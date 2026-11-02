<?php

declare(strict_types=1);

use App\Database\Models\Account\User;
use Tinvar\Accounts\CredentialEpochs;
use Tinvar\Orm\Migrations\Migration;
use Tinvar\Orm\Schema\Schema;

/**
 * Each user's credential epoch (ADR 0153): moved when they sign out
 * everywhere, reset or change their password, change their address or turn
 * two-factor off, and what their API tokens are held to. A database the
 * accounts migration made before the table was in the kit gains it here;
 * a new one already has it.
 */
return new class extends Migration {
    public function up(Schema $schema): void
    {
        CredentialEpochs::define($schema, User::class, ifNotExists: true);
    }

    public function down(Schema $schema): void
    {
        // The kit's tables, this one among them, go with the accounts migration's down().
    }
};
