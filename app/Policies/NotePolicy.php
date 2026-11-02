<?php

declare(strict_types=1);

namespace App\Policies;

use App\Auth\Admin\Admins;
use App\Database\Models\Note;
use Tinvar\Attributes\Policy;
use Tinvar\Auth\Authenticatable;

/**
 * Who may do what with notes: #[Policy] is how discovery registers it for
 * Note. Every #[Authorize] naming a note asks here, before any loader,
 * query body or action body runs; an ability with no method here refuses
 * everyone.
 *
 * Reading the notes, and leaving one, are for anyone, signed in or not --
 * $user is the signed-in App\Database\Models\Account\User, or null -- and
 * the actions' throttle and AddNoteRequest bound them. To keep notes to
 * people with accounts, say so here, and take #[PublicAccess] off the
 * query and the action.
 *
 * Changing or deleting a note is for the app's admins alone (Admins): what
 * the admin panel's NoteResource asks, after the panel's own guard.
 */
#[Policy(Note::class)]
final readonly class NotePolicy
{
    public function __construct(private Admins $admins) {}

    public function viewAny(?Authenticatable $user): bool
    {
        return true;
    }

    public function view(?Authenticatable $user, Note $note): bool
    {
        return true;
    }

    public function create(?Authenticatable $user): bool
    {
        return true;
    }

    public function update(?Authenticatable $user, Note $note): bool
    {
        return $this->admins->allows($user);
    }

    public function delete(?Authenticatable $user, Note $note): bool
    {
        return $this->admins->allows($user);
    }
}
