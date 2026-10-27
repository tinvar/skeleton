<?php

declare(strict_types=1);

namespace App\Models;

use Tinvar\Orm\Attributes\Column;
use Tinvar\Orm\Attributes\Id;
use Tinvar\Orm\Attributes\Table;
use Tinvar\Orm\Model;

/**
 * A note someone left on the home page.
 *
 * A model never crosses to the renderer: what a page or a query sends is a
 * Data object with the fields the view needs (App\Data\NoteData), and the
 * encoder refuses anything else.
 */
#[Table('notes')]
final class Note extends Model
{
    #[Id] public private(set) string $id;
    #[Column] public string $body;

    /** UTC, ISO 8601, so it sorts as text. */
    #[Column] public string $createdAt;
}
