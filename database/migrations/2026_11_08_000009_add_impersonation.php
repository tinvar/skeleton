<?php

declare(strict_types=1);

use App\Database\Models\Account\User;
use Tinvar\Accounts\Impersonation\Impersonations;
use Tinvar\Accounts\Sessions;
use Tinvar\Orm\Migrations\Migration;
use Tinvar\Orm\Schema\Schema;

/**
 * What impersonation keeps (ADR 0194): the impersonation a session is, and
 * the impersonations themselves -- who is acting as whom, why, and until
 * when. AccountsKit::migrate() makes neither, so every app's accounts
 * migration makes what it always made. Nothing uses them until the Kernel
 * gives the kit `impersonation:`.
 */
return new class extends Migration {
    public function up(Schema $schema): void
    {
        Sessions::addImpersonation($schema);
        Impersonations::define($schema, User::class);
    }

    public function down(Schema $schema): void
    {
        $schema->drop(Impersonations::TABLE);
        Sessions::dropImpersonation($schema);
    }
};
