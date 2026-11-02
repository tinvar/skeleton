'use client';

import { useEffect, useState } from 'react';
import * as stylex from '@stylexjs/stylex';
import { Badge, Banner, Button, Dialog, Field, Input, Text } from '@meridian/ui';
import { RecoveryCodes } from '@meridian/ui/auth';
import { colors, radius, space } from '@meridian/ui/tokens.stylex';
import { Form, type HttpFailure } from '@tinvar/react/form';
import { clearHistory, useForm } from '@tinvar/react/client';
import { signOut, signOutEverywhere } from '@actions/SignInActions';
import { addPasskey, passkeyOptions, removePasskey } from '@actions/PasskeyActions';
import {
  beginTwoFactor,
  confirmTwoFactor,
  disableTwoFactor,
  regenerateRecoveryCodes,
} from '@actions/TwoFactorActions';
import { createPasskey, passkeysSupported } from '../../lib/passkeys';
import type { PasskeyData, TwoFactorSetupData } from '../../types/generated';
import { describe, messageOf } from './AccountForms';
import { useConfirm } from './ConfirmIdentity';

/**
 * Signs out -- of this browser, or with `everywhere`, of every device --
 * then loads the home page whole: a client visit would keep what the
 * router and the components still held for the session, and logout clears
 * what was remembered for it from the tab first (clearHistory()).
 */
export function SignOutButton({ everywhere = false }: { everywhere?: boolean }) {
  const [busy, setBusy] = useState(false);
  const [open, setOpen] = useState(false);
  const [failed, setFailed] = useState(false);

  const leave = async () => {
    setBusy(true);
    setFailed(false);
    try {
      const result = await (everywhere ? signOutEverywhere({}) : signOut({}));
      const next = (result.value as { next?: unknown } | null)?.next;
      clearHistory();
      window.location.assign(typeof next === 'string' ? next : '/');
    } catch {
      setBusy(false);
      setFailed(true);
    }
  };

  if (!everywhere) {
    return (
      <Button variant="ghost" size="sm" disabled={busy} onClick={() => void leave()}>
        {busy ? 'Signing out…' : 'Sign out'}
      </Button>
    );
  }

  return (
    <>
      {failed && (
        <Banner tone="bad" title="Not signed out">
          The app did not answer, so every device is still signed in. Check your connection and try again.
        </Banner>
      )}
      <Button variant="secondary" onClick={() => setOpen(true)}>
        Sign out everywhere
      </Button>
      <Dialog
        open={open}
        onClose={() => {
          if (!busy) setOpen(false);
        }}
        size="sm"
        title="Sign out everywhere?"
        description="Every device signed in to this account is signed out, this one included, and links already emailed to you stop working. Nothing else changes."
        footer={
          <>
            <Button variant="secondary" onClick={() => setOpen(false)} disabled={busy}>
              Cancel
            </Button>
            <Button variant="primary" onClick={() => void leave()} disabled={busy}>
              {busy ? 'Signing out…' : 'Sign out everywhere'}
            </Button>
          </>
        }
      />
    </>
  );
}

/**
 * The account's passkeys: each listed with a way to remove it, and one to
 * add another -- either once this session has confirmed it is its owner's
 * (useConfirm()). The ceremony itself is the browser's; the answer is
 * posted through a form state (useForm), so the page follows it -- the
 * action re-renders the list -- as a submitted form's would.
 */
export function Passkeys({ passkeys }: { passkeys: PasskeyData[] }) {
  const [busy, setBusy] = useState(false);
  const [failure, setFailure] = useState<string | null>(null);
  // Known only in the browser: drawn as unsupported on the server and on
  // the first render, so hydration finds what the server sent.
  const [supported, setSupported] = useState(false);
  useEffect(() => setSupported(passkeysSupported()), []);
  const form = useForm({ name: '' });
  const { confirmFirst, refusedForConfirmation } = useConfirm();
  const failed = (fallback: string) => ({
    onError: (errors: Record<string, string[]>) => {
      if (!refusedForConfirmation(errors)) setFailure(Object.values(errors)[0]?.[0] ?? fallback);
    },
    onHttpException: (f: HttpFailure) => setFailure(describe(f).detail),
    onNetworkError: () => setFailure(describe(null).detail),
  });

  const add = async () => {
    setFailure(null);
    if (!(await confirmFirst())) return;
    setBusy(true);
    try {
      const options = (await passkeyOptions({})).value as Record<string, unknown>;
      const credential = await createPasskey(options);
      form.transform((data) => ({ name: data.name.trim(), credential }));
      await form.submit(addPasskey, {
        ...failed('The passkey was not added. Try again.'),
        resetOnSuccess: true,
      });
    } catch (error) {
      const errors = (error as { errors?: Record<string, string[]> } | null)?.errors;
      if (!refusedForConfirmation(errors))
        setFailure(messageOf(error, 'The passkey was not added. Try again.'));
    } finally {
      setBusy(false);
    }
  };

  const remove = async (id: string) => {
    setFailure(null);
    if (!(await confirmFirst())) return;
    form.transform(() => ({ id }));
    await form.submit(removePasskey, failed('The passkey was not removed. Try again.'));
  };

  return (
    <div {...stylex.props(styles.stack)}>
      {failure && (
        <Banner tone="bad" title="Passkeys">
          {failure}
        </Banner>
      )}
      {passkeys.length === 0 ? (
        <Text tone="secondary">
          No passkeys yet. A passkey signs you in with your fingerprint, face or device PIN, and cannot be
          phished.
        </Text>
      ) : (
        <ul {...stylex.props(styles.list)} aria-label="Your passkeys">
          {passkeys.map((passkey) => (
            <li key={passkey.id} {...stylex.props(styles.item)}>
              <div {...stylex.props(styles.itemText)}>
                <Text weight={600}>{passkey.name}</Text>
                <Text size="sm" tone="secondary">
                  {`Added ${day(passkey.createdAt)}${passkey.lastUsedAt ? `, last used ${day(passkey.lastUsedAt)}` : ', not used yet'}`}
                </Text>
              </div>
              {passkey.backedUp && <Badge>Synced</Badge>}
              <Button
                variant="ghost"
                size="sm"
                onClick={() => void remove(passkey.id)}
                aria-label={`Remove ${passkey.name}`}
              >
                Remove
              </Button>
            </li>
          ))}
        </ul>
      )}
      {supported ? (
        <div {...stylex.props(styles.row)}>
          <Field label="Name for the new passkey" hint="So you can tell them apart, as Laptop or Phone.">
            <Input
              value={form.data.name}
              onChange={(e) => form.setData('name', e.currentTarget.value)}
              maxLength={100}
            />
          </Field>
          <Button onClick={() => void add()} disabled={busy}>
            {busy ? 'Waiting for your device…' : 'Add a passkey'}
          </Button>
        </div>
      ) : (
        <Text size="sm" tone="secondary">
          This browser can't make passkeys. Open the account page in one that can.
        </Text>
      )}
    </div>
  );
}

/** The day of a moment, as UTC writes it: the same on the server and in the browser. */
function day(moment: string): string {
  return moment.slice(0, 10);
}

/**
 * Two-factor sign-in: off, half-way set up -- the secret for the
 * authenticator app and the code that turns it on -- or on, with recovery
 * codes to replace and a way to turn it off. Setting it up asks this
 * session to confirm it is its owner's first (useConfirm()). Recovery codes
 * are shown once, as the action answers with them: only their hashes are
 * kept.
 */
export function TwoFactor({
  enabled,
  setup,
  recoveryCodesLeft,
}: {
  enabled: boolean;
  setup: TwoFactorSetupData | null;
  recoveryCodesLeft: number;
}) {
  const [codes, setCodes] = useState<string[] | null>(null);
  const [failure, setFailure] = useState<string | null>(null);
  const [starting, setStarting] = useState(false);
  const { confirmFirst, refusedForConfirmation } = useConfirm();
  const begin = useForm({});
  const start = async () => {
    setFailure(null);
    if (!(await confirmFirst())) return;
    setStarting(true);
    try {
      await begin.submit(beginTwoFactor, {
        onError: (errors: Record<string, string[]>) => {
          if (!refusedForConfirmation(errors))
            setFailure(Object.values(errors)[0]?.[0] ?? 'Two-factor sign-in was not set up. Try again.');
        },
        onHttpException: (f: HttpFailure) => setFailure(describe(f).detail),
        onNetworkError: () => setFailure(describe(null).detail),
      });
    } finally {
      setStarting(false);
    }
  };
  const keep = (value: unknown) => {
    const shown = (value as { recoveryCodes?: unknown } | null)?.recoveryCodes;
    if (Array.isArray(shown)) setCodes(shown.filter((code): code is string => typeof code === 'string'));
  };

  if (codes) {
    return (
      <div {...stylex.props(styles.stack)}>
        <Banner tone="good" title="Keep these somewhere safe">
          Each recovery code signs you in once if you lose your authenticator app. They are shown only now.
        </Banner>
        <RecoveryCodes codes={codes} brand="Tinvar app" onDone={() => setCodes(null)} />
      </div>
    );
  }

  if (enabled) {
    return (
      <div {...stylex.props(styles.stack)}>
        <Text>{`Two-factor sign-in is on. ${recoveryCodesLeft} recovery ${recoveryCodesLeft === 1 ? 'code is' : 'codes are'} left.`}</Text>
        <Form
          onSuccess={(result) => keep(result.value)}
          {...stylex.props(styles.row)}
          action={regenerateRecoveryCodes}
        >
          {(slot) => (
            <>
              <Field label="Current password" error={slot.errors.currentPassword}>
                <Input type="password" name="currentPassword" autoComplete="current-password" required />
              </Field>
              <Button type="submit" disabled={slot.processing}>
                New recovery codes
              </Button>
            </>
          )}
        </Form>
        <Form {...stylex.props(styles.row)} action={disableTwoFactor}>
          {(slot) => (
            <>
              <Field label="Current password" error={slot.errors.currentPassword}>
                <Input type="password" name="currentPassword" autoComplete="current-password" required />
              </Field>
              <Button type="submit" variant="danger" disabled={slot.processing}>
                Turn two-factor off
              </Button>
            </>
          )}
        </Form>
      </div>
    );
  }

  if (setup) {
    return (
      <div {...stylex.props(styles.stack)}>
        <Text>
          Add this account to your authenticator app with the key below, then type the code it shows.
        </Text>
        <code {...stylex.props(styles.secret)} aria-label="Setup key">
          {setup.secret.replace(/(.{4})/g, '$1 ').trim()}
        </code>
        <Text size="sm" tone="secondary">
          <a href={setup.uri}>Open it in an authenticator app on this device</a>
        </Text>
        {failure && (
          <Banner tone="bad" title="Not turned on">
            {failure}
          </Banner>
        )}
        <Form
          onSuccess={(result) => keep(result.value)}
          onError={(errors) => void refusedForConfirmation(errors)}
          onHttpException={(f) => setFailure(describe(f).detail)}
          {...stylex.props(styles.stack)}
          action={confirmTwoFactor}
        >
          {(slot) => (
            <>
              <Field label="Code from the app" error={slot.errors.code}>
                <Input name="code" autoComplete="one-time-code" inputMode="numeric" maxLength={6} required />
              </Field>
              <Button type="submit" variant="primary" disabled={slot.processing}>
                Turn two-factor on
              </Button>
            </>
          )}
        </Form>
      </div>
    );
  }

  return (
    <div {...stylex.props(styles.stack)}>
      <Text tone="secondary">
        Ask for a code from an authenticator app each time you sign in with your password.
      </Text>
      {failure && (
        <Banner tone="bad" title="Not set up">
          {failure}
        </Banner>
      )}
      <div>
        <Button disabled={starting} onClick={() => void start()}>
          Set up two-factor sign-in
        </Button>
      </div>
    </div>
  );
}

const styles = stylex.create({
  stack: { display: 'flex', flexDirection: 'column', rowGap: space.s4 },
  row: { alignItems: 'flex-end', columnGap: space.s3, display: 'flex', flexWrap: 'wrap', rowGap: space.s3 },
  buttons: { columnGap: space.s3, display: 'flex', flexWrap: 'wrap', rowGap: space.s3 },
  list: {
    display: 'flex',
    flexDirection: 'column',
    listStyle: 'none',
    margin: 0,
    padding: 0,
    rowGap: space.s2,
  },
  item: {
    alignItems: 'center',
    borderColor: colors.border,
    borderRadius: radius.lg,
    borderStyle: 'solid',
    borderWidth: '1px',
    columnGap: space.s3,
    display: 'flex',
    paddingBlock: space.s3,
    paddingInline: space.s4,
  },
  itemText: { display: 'flex', flexDirection: 'column', flexGrow: 1, rowGap: space.s1 },
  secret: {
    backgroundColor: colors.subtle,
    borderRadius: radius.md,
    fontFamily: 'monospace',
    fontSize: '15px',
    letterSpacing: '0.05em',
    paddingBlock: space.s3,
    paddingInline: space.s4,
    wordBreak: 'break-all',
  },
});
