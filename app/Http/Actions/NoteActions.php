<?php

declare(strict_types=1);

namespace App\Http\Actions;

use App\Database\Models\Note;
use App\Http\Requests\AddNoteRequest;
use Tinvar\Actions\ActionResult;
use Tinvar\Attributes\Action;
use Tinvar\Attributes\Authorize;
use Tinvar\Attributes\PublicAccess;
use Tinvar\Auth\AuthContext;
use Tinvar\Http\HttpException;
use Tinvar\Http\Request;
use Tinvar\Http\Throttle;
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
    /** How many notes a visitor may leave from one address in an hour. */
    public const int VISITOR_NOTES_PER_HOUR = 10;

    public function __construct(
        private readonly Orm $orm,
        private readonly AuthContext $auth,
        private readonly Throttle $throttle,
    ) {}

    // Public: anyone may leave a note, signed in or not. An action requires
    // a signed-in user unless it says otherwise, and this says so in the
    // open; take it off to keep notes to people with accounts.
    #[Action(name: 'notes.add'), PublicAccess('Anyone may leave a note, signed in or not.'), Authorize('create', Note::class)]
    public function add(AddNoteRequest $note, Request $request): ActionResult
    {
        $body = trim($note->body);
        if ($body === '') {
            throw new ValidationException(['body' => ['Write the note before you add it.']]);
        }

        // A public write is a budgeted one: a visitor leaves a few notes an
        // hour from one address, past the actions' throttle every caller
        // spends. Someone signed in is throttled by their account there.
        if (!$this->auth->check()) {
            $budget = $this->throttle->hitAddress('notes.visitor', $request, self::VISITOR_NOTES_PER_HOUR, 3600);
            if (!$budget->allowed) {
                throw new HttpException(429, 'Too many notes from this address: sign in, or wait a while.', 'too_many_notes', $budget->headers());
            }
        }

        $saved = new Note();
        $saved->body = $body;
        // Its createdAt is stamped by the ORM as it is inserted.
        $this->orm->save($saved);

        // The page the browser has open, rendered again through its whole
        // GET pipeline and sent back with this answer.
        return ActionResult::revalidate('home');
    }
}
