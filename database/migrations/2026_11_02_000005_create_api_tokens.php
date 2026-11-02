<?php

declare(strict_types=1);

use Tinvar\Api\DatabaseApiTokens;
use Tinvar\Orm\Migrations\Migration;
use Tinvar\Orm\Schema\Schema;

/**
 * The accounts' API tokens (ADR 0159): each one's record and the SHA-256
 * of its secret, never the secret, bound to its user and their credential
 * epoch, the panel and the host it was issued on.
 */
return new class extends Migration {
    public function up(Schema $schema): void
    {
        DatabaseApiTokens::define($schema, ifNotExists: true);
    }

    public function down(Schema $schema): void
    {
        $schema->drop('api_tokens');
    }
};
