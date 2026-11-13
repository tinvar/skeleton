import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { act, cleanup, fireEvent, render, screen } from '@testing-library/react';
import { actionFetch, calls, refuse, resetMocks } from '@tinvar/react/testing';
import { add } from '@actions/NoteActions';
import { NoteForm } from '../../resources/components/NoteForm';

/**
 * <NoteForm> posts to `notes.add` through <Form>. Here the post reaches the
 * mock of that action codegen wrote, through actionFetch, and goes no
 * further: no server, no database.
 */

beforeEach(() => {
  vi.stubGlobal('fetch', actionFetch);
});

afterEach(() => {
  cleanup();
  resetMocks();
  vi.unstubAllGlobals();
});

async function addNote(body: string) {
  fireEvent.change(screen.getByLabelText('Note'), { target: { value: body } });
  await act(async () => {
    screen.getByRole('button', { name: 'Add note' }).closest('form')!.requestSubmit();
  });
}

describe('NoteForm', () => {
  it('posts the note to the action', async () => {
    render(<NoteForm />);

    await addNote('Remember the milk.');

    expect(calls(add)).toEqual([{ body: 'Remember the milk.' }]);
  });

  it('puts what the action refused on the field', async () => {
    refuse(add, { errors: { body: ['Write the note before you add it.'] } });
    render(<NoteForm />);

    await addNote(' ');

    expect(await screen.findByText('Write the note before you add it.')).toBeTruthy();
  });

  it('says why when the action is throttled', async () => {
    refuse(add, { status: 429 });
    render(<NoteForm />);

    await addNote('One too many.');

    expect(
      await screen.findByText(
        'Too many notes from this address. Wait a while, or sign in, then add it again.',
      ),
    ).toBeTruthy();
  });
});
