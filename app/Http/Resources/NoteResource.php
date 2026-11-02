<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Auth\Admin\Admins;
use App\Database\Models\Note;
use Tinvar\Attributes\AdminResource;
use Tinvar\Resources\BulkAction;
use Tinvar\Resources\Column;
use Tinvar\Resources\Field;
use Tinvar\Resources\Resource;
use Tinvar\Resources\Sort;

/**
 * The notes, in the admin panel (ADR 0152): a list at /admin/notes,
 * searchable by what they say, each note's page, and forms to change one,
 * add one or delete several. One class, registered by discovery as four
 * pages and four actions -- each behind the panel's guard (RequireAdmin)
 * and asking NotePolicy -- and drawn by the panel's three views,
 * resources/pages/admin/resources/.
 *
 * `bin/tinvar make:resource Orders/Order --fields=... --panel=admin`
 * writes one of these beside a model's own pages.
 */
#[AdminResource(model: Note::class, panel: Admins::PANEL, path: '/admin/notes')]
final class NoteResource extends Resource
{
    public static function columns(): array
    {
        return [
            Column::make('body', 'Note')->searchable(),
            Column::make('createdAt', 'Added')->sortable(),
        ];
    }

    public static function fields(): array
    {
        return [
            Field::make('body', 'Note')->textarea()->max(500),
        ];
    }

    public static function bulkActions(): array
    {
        return [BulkAction::delete()];
    }

    public static function sort(): Sort
    {
        return Sort::desc('createdAt');
    }
}
