<?php

declare(strict_types=1);

namespace App\Http\Actions;

use App\Database\Models\Note;
use App\Http\Requests\AddNoteRequest;
use Tinvar\Actions\ActionResult;
use Tinvar\Attributes\Action;
use Tinvar\Attributes\Authorize;
use Tinvar\Attributes\PublicAccess;
use Tinvar\Orm\Orm;
use Tinvar\Validation\ValidationException;

/**
 * Writing notes. A form posts here with the session's CSRF token, through
 * the actions' throttle; the policy is asked and the request validated
 * before the body runs. Codegen writes `@actions/NoteActions`, whose `add`
 * is what <NoteForm> posts to.
 */
final class NoteActions
{
    public function __construct(private readonly Orm $orm) {}

    // Public: the app has no accounts, so no one could sign in to post.
    // An action requires a signed-in user unless it says otherwise, and
    // this says so in the open; take it off once the app has accounts.
    #[Action(name: 'notes.add'), PublicAccess('Anyone may leave a note: the app has no accounts yet.'), Authorize('create', Note::class)]
    public function add(AddNoteRequest $request): ActionResult
    {
        $body = trim($request->body);
        if ($body === '') {
            throw new ValidationException(['body' => ['Write the note before you add it.']]);
        }

        $note = new Note();
        $note->body = $body;
        // Its createdAt is stamped by the ORM as it is inserted.
        $this->orm->save($note);

        // The page the browser has open, rendered again through its whole
        // GET pipeline and sent back with this answer.
        return ActionResult::revalidate('home');
    }
}
