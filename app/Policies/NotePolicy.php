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
 * The app has no accounts yet, so $user is always null, and these say yes
 * to anyone: reading the notes, and leaving one, which the actions'
 * throttle and AddNoteRequest bound. Once the app has accounts, say here
 * who may.
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
