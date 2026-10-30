'use client';

import { useState } from 'react';
import * as stylex from '@stylexjs/stylex';
import { Banner, Button, Field, Textarea } from '@meridian/ui';
import { space } from '@meridian/ui/tokens.stylex';
import { Form } from '@tinvar/react/form';
import { add } from '@actions/NoteActions';

/**
 * A note, posted to App\Actions\NoteActions::add().
 *
 * A real form: before JavaScript loads it is an ordinary post, with the
 * session's CSRF token in a hidden field, and a mistake comes back as the
 * page with the error on its field. Once hydrated it submits in place, the
 * action's answer re-renders the page, and the list shows the new note.
 */
export function NoteForm() {
  const [failed, setFailed] = useState<string | null>(null);

  return (
    <Form
      resetOnSuccess
      onStart={() => setFailed(null)}
      onHttpException={(failure) =>
        setFailed(
          failure.status === 429
            ? 'Too many notes from this address. Wait a minute, then add it again.'
            : `The note was not saved (${failure.status}). Add it again; quote ${failure.reference ?? 'the time'} if it keeps failing.`,
        )
      }
      onNetworkError={() =>
        setFailed(
          'The note was not saved: the server did not answer. Check the connection, then add it again.',
        )
      }
      {...stylex.props(styles.form)}
      action={add}
    >
      {(form) => (
        <>
          {failed && (
            <Banner tone="bad" title="Note not saved">
              {failed}
            </Banner>
          )}
          <Field label="Note" hint="Up to 500 characters." error={form.errors.body}>
            <Textarea name="body" rows={3} maxLength={500} required />
          </Field>
          <div {...stylex.props(styles.actions)}>
            <Button type="submit" variant="primary" disabled={form.processing}>
              Add note
            </Button>
          </div>
        </>
      )}
    </Form>
  );
}

const styles = stylex.create({
  form: { display: 'flex', flexDirection: 'column', rowGap: space.s4 },
  actions: { display: 'flex', justifyContent: 'flex-end' },
});
