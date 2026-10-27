<?php

declare(strict_types=1);

namespace App\Requests;

use Tinvar\Validation\FormRequest;
use Tinvar\Validation\Rules\Max;
use Tinvar\Validation\Rules\Min;

/**
 * What NoteActions::add() is posted. The typed properties are the schema,
 * and the rule attributes say what a type cannot; a post that fails them
 * never reaches the action, and its errors go back to their fields.
 */
final readonly class AddNoteRequest extends FormRequest
{
    public function __construct(
        #[Min(1), Max(500)]
        public string $body,
    ) {}
}
