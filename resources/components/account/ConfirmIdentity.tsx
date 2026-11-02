'use client';

import { createContext, useCallback, useContext, useEffect, useRef, useState, type ReactNode } from 'react';
import * as stylex from '@stylexjs/stylex';
import { Banner, Button, Dialog, Field, Input, Text } from '@meridian/ui';
import { space } from '@meridian/ui/tokens.stylex';
import { Form } from '@tinvar/react/form';
import {
  confirmPasskeyOptions,
  confirmWithCode,
  confirmWithPasskey,
  confirmWithPassword,
} from '@actions/ConfirmActions';
import { passkeysSupported, signWithPasskey } from '../../lib/passkeys';
import { describe, messageOf } from './AccountForms';

/**
 * "Confirm it's you" (ADR 0153). Adding a passkey, turning two-factor on and
 * creating an API token need this session to have proved it is its owner's in the last five
 * minutes -- a signed-in session alone may not, or a stolen cookie could
 * add a way back in that outlives it. The server decides; this asks first
 * when the page says the last confirmation has lapsed, and again whenever
 * an action is refused for it.
 *
 * A Meridian dialog with each way the account can prove it: one of its
 * passkeys, a code from its authenticator app, or its password.
 */

type Confirm = {
  /** Resolves true once this session is confirmed -- at once if it still is -- and false if the person cancels. */
  confirmFirst: () => Promise<boolean>;
  /** For an action's field errors: true, and the dialog opened, when it was refused for want of a confirmation. */
  refusedForConfirmation: (errors: Record<string, string[] | string> | undefined) => boolean;
};

const ConfirmContext = createContext<Confirm | null>(null);

export function useConfirm(): Confirm {
  const confirm = useContext(ConfirmContext);
  if (!confirm) throw new Error('useConfirm() is used inside <ConfirmProvider>.');
  return confirm;
}

/** A little short of the server's five minutes, for the clocks not agreeing. */
const MARGIN_SECONDS = 15;
const LIFETIME_SECONDS = 300;

export function ConfirmProvider({
  confirmedUntil,
  hasPasskeys,
  twoFactorEnabled,
  children,
}: {
  confirmedUntil: number | null;
  hasPasskeys: boolean;
  twoFactorEnabled: boolean;
  children: ReactNode;
}) {
  const [open, setOpen] = useState(false);
  const [busy, setBusy] = useState(false);
  const [failure, setFailure] = useState<string | null>(null);
  const [supported, setSupported] = useState(false);
  useEffect(() => setSupported(passkeysSupported()), []);
  // What this tab knows beyond the page's data: a confirmation it just made.
  const until = useRef<number | null>(confirmedUntil);
  useEffect(() => {
    until.current = confirmedUntil;
  }, [confirmedUntil]);
  const waiting = useRef<((confirmed: boolean) => void) | null>(null);

  const finish = useCallback((confirmed: boolean) => {
    if (confirmed) until.current = Math.floor(Date.now() / 1000) + LIFETIME_SECONDS;
    setOpen(false);
    setBusy(false);
    setFailure(null);
    waiting.current?.(confirmed);
    waiting.current = null;
  }, []);

  const ask = useCallback(
    () =>
      new Promise<boolean>((resolve) => {
        waiting.current?.(false);
        waiting.current = resolve;
        setFailure(null);
        setOpen(true);
      }),
    [],
  );

  const confirmFirst = useCallback(() => {
    const still = until.current !== null && until.current - MARGIN_SECONDS > Date.now() / 1000;
    return still ? Promise.resolve(true) : ask();
  }, [ask]);

  const refusedForConfirmation = useCallback(
    (errors: Record<string, string[] | string> | undefined) => {
      if (!errors || !('confirm' in errors)) return false;
      until.current = null;
      void ask();
      return true;
    },
    [ask],
  );

  const withPasskey = async () => {
    setBusy(true);
    setFailure(null);
    try {
      const options = (await confirmPasskeyOptions({})).value as Record<string, unknown>;
      const credential = await signWithPasskey(options);
      await confirmWithPasskey({ credential });
      finish(true);
    } catch (error) {
      setBusy(false);
      setFailure(messageOf(error, "The passkey didn't confirm it's you. Try again, or type your password."));
    }
  };

  return (
    <ConfirmContext.Provider value={{ confirmFirst, refusedForConfirmation }}>
      {children}
      <Dialog
        open={open}
        onClose={() => {
          if (!busy) finish(false);
        }}
        size="sm"
        title="Confirm it's you"
        description="Adding a passkey, turning on two-factor sign-in or creating an API token changes how this account can be reached, so we check it's you first. It lasts five minutes, on this device."
        footer={
          <Button variant="secondary" onClick={() => finish(false)} disabled={busy}>
            Cancel
          </Button>
        }
      >
        <div {...stylex.props(styles.stack)}>
          {failure && (
            <Banner tone="bad" title="Not confirmed">
              {failure}
            </Banner>
          )}
          {hasPasskeys && supported && (
            <Button variant="primary" disabled={busy} onClick={() => void withPasskey()}>
              {busy ? 'Waiting for your passkey…' : 'Use a passkey'}
            </Button>
          )}
          {twoFactorEnabled && (
            <Form
              onSuccess={() => finish(true)}
              onHttpException={(f) => setFailure(describe(f).detail)}
              {...stylex.props(styles.row)}
              action={confirmWithCode}
            >
              {(slot) => (
                <>
                  <Field label="Code from your authenticator app" error={slot.errors.code}>
                    <Input
                      name="code"
                      autoComplete="one-time-code"
                      inputMode="numeric"
                      maxLength={6}
                      required
                    />
                  </Field>
                  <Button type="submit" disabled={slot.processing}>
                    Confirm with the code
                  </Button>
                </>
              )}
            </Form>
          )}
          {(hasPasskeys || twoFactorEnabled) && <Text tone="secondary">Or type your password.</Text>}
          <Form
            onSuccess={() => finish(true)}
            onHttpException={(f) => setFailure(describe(f).detail)}
            {...stylex.props(styles.row)}
            action={confirmWithPassword}
          >
            {(slot) => (
              <>
                <Field label="Your password" error={slot.errors.currentPassword}>
                  <Input type="password" name="currentPassword" autoComplete="current-password" required />
                </Field>
                <Button
                  type="submit"
                  variant={hasPasskeys && supported ? 'secondary' : 'primary'}
                  disabled={slot.processing}
                >
                  {slot.processing ? 'Checking…' : 'Confirm'}
                </Button>
              </>
            )}
          </Form>
        </div>
      </Dialog>
    </ConfirmContext.Provider>
  );
}

const styles = stylex.create({
  stack: { display: 'flex', flexDirection: 'column', rowGap: space.s4 },
  row: { alignItems: 'flex-end', columnGap: space.s3, display: 'flex', flexWrap: 'wrap', rowGap: space.s3 },
});
