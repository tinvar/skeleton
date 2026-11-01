<?php

declare(strict_types=1);

namespace App\Policies;

use App\Database\Models\Note;
use Tinvar\Attributes\Policy;
use Tinvar\Auth\Authenticatable;

/**
 * Who may do what with notes: #[Policy] is how discovery registers it for
 * Note. Every #[Authorize] naming a note asks here, before any loader,
 * query body or action body runs; an ability with no method here refuses
 * everyone.
 *
 * These say yes to anyone, signed in or not -- $user is the signed-in
 * App\Database\Models\Account\User, or null: reading the notes, and
 * leaving one, which the actions' throttle and AddNoteRequest bound. To
 * keep notes to people with accounts, say so here, and take #[PublicAccess]
 * off the query and the action.
 */
#[Policy(Note::class)]
final class NotePolicy
{
    public function viewAny(?Authenticatable $user): bool
    {
        return true;
    }

    public function create(?Authenticatable $user): bool
    {
        return true;
    }
}
