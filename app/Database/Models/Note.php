<?php

declare(strict_types=1);

namespace App\Database\Models;

use Carbon\CarbonImmutable;
use Tinvar\Orm\Attributes\Column;
use Tinvar\Orm\Attributes\Id;
use Tinvar\Orm\Attributes\Table;
use Tinvar\Orm\Model;

/**
 * A note someone left on the home page.
 *
 * A model never crosses to the renderer: what a page or a query sends is a
 * Data object with the fields the view needs (App\Http\Data\NoteData), and the
 * encoder refuses anything else.
 */
#[Table('notes')]
final class Note extends Model
{
    #[Id] public private(set) string $id;
    #[Column] public string $body;

    /**
     * When it was added, in UTC: stamped by the ORM on insert, to the
     * microsecond, so two notes in one second still list in the order they
     * were added (ADR 0145).
     */
    #[Column] public CarbonImmutable $createdAt;
}
