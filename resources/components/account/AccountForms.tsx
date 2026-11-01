'use client';

import { useEffect, useState, type ReactNode } from 'react';
import * as stylex from '@stylexjs/stylex';
import { Banner, Button, Field, Input } from '@meridian/ui';
import { space } from '@meridian/ui/tokens.stylex';
import { Form, type HttpFailure } from '@tinvar/react/form';
import type { ActionTarget } from '@tinvar/react/action';
import { completeSignUp, requestSignUp } from '@actions/SignUpActions';
import {
  completeSecondFactor,
  passkeySignInOptions,
  signIn,
  signInWithPasskey,
} from '@actions/SignInActions';
import { changePassword, requestPasswordReset, resetPassword } from '@actions/PasswordActions';
import { confirmEmailChange, requestEmailChange } from '@actions/EmailActions';
import { passkeysSupported, signWithPasskey } from '../../lib/passkeys';

/**
 * The account forms (ADR 0150). Each is a real form posting to its action,
 * so it works before script loads: a mistake comes back as the page with
 * its errors on the fields. Once hydrated it submits in place.
 *
 * A link's token travels in a hidden field: the page the link landed on
 * only looked at it, and only this POST uses it. Every way a link can be
 * refused comes back as one message, on the token.
 */

type Failure = { title: string; detail: string };

/** What went wrong that is no field's fault, in words that say what to do. */
export function describe(failure: HttpFailure | null): Failure {
  if (failure === null) {
    return {
      title: "Couldn't reach the app",
      detail: 'Nothing was sent. Check your connection and try again.',
    };
  }
  if (failure.status === 429) {
    const minutes = Math.max(1, Math.ceil((failure.retryAfter ?? 60) / 60));
    return {
      title: 'Too many attempts',
      detail: `This is paused to stop guessing. Wait ${minutes} ${minutes === 1 ? 'minute' : 'minutes'}, then try again.`,
    };
  }
  return {
    title: 'Something went wrong',
    detail: `The app ran into an error. Try again.${failure.reference ? ` Quote reference ${failure.reference} if it keeps happening.` : ''}`,
  };
}

const EXPIRED: Failure = {
  title: 'This page has expired',
  detail: 'It was open longer than a session lasts, so it has been loaded again. Submit it once more.',
};

/** A form with the banner every account form shows for what is not a field's fault. */
function AccountForm({
  action,
  children,
  onSuccess,
}: {
  action: ActionTarget;
  children: (slot: {
    errors: Record<string, string>;
    processing: boolean;
    old: (field: string) => string;
  }) => ReactNode;
  onSuccess?: (value: unknown) => void;
}) {
  const [failure, setFailure] = useState<Failure | null>(null);

  return (
    <Form
      noValidate
      onStart={() => setFailure(null)}
      onHttpException={(f) => setFailure(describe(f))}
      onNetworkError={() => setFailure(describe(null))}
      {...(onSuccess ? { onSuccess: (result) => onSuccess(result.value) } : {})}
      {...stylex.props(styles.form)}
      action={action}
    >
      {(slot) => {
        const banner = slot.expired ? EXPIRED : failure;
        return (
          <>
            {banner && (
              <Banner tone="bad" title={banner.title}>
                {banner.detail}
              </Banner>
            )}
            {slot.errors.token && (
              <Banner tone="bad" title="This link can't be used">
                {slot.errors.token}
              </Banner>
            )}
            {children({
              errors: slot.errors,
              processing: slot.processing,
              old: (field) => slot.old(field, '') ?? '',
            })}
          </>
        );
      }}
    </Form>
  );
}

function Submit({ busy, children }: { busy: boolean; children: ReactNode }) {
  return (
    <Button type="submit" variant="primary" size="lg" disabled={busy} style={styles.full}>
      {children}
    </Button>
  );
}

function EmailField({
  error,
  old,
  label = 'Email',
}: {
  error: string | undefined;
  old: string;
  label?: string;
}) {
  return (
    <Field label={label} error={error ? 'Enter a full email address, like name@example.com.' : undefined}>
      <Input
        type="email"
        name="email"
        autoComplete="email"
        inputMode="email"
        placeholder="name@example.com"
        defaultValue={old}
        required
      />
    </Field>
  );
}

function NewPasswordField({
  error,
  minLength,
  label = 'New password',
}: {
  error: string | undefined;
  minLength: number;
  label?: string;
}) {
  return (
    <Field
      label={label}
      hint={`At least ${minLength} characters. A few words you will remember is a good one.`}
      error={error}
    >
      <Input type="password" name="password" autoComplete="new-password" minLength={minLength} required />
    </Field>
  );
}

export function SignUpForm() {
  return (
    <AccountForm action={requestSignUp}>
      {({ errors, processing, old }) => (
        <>
          <Field label="Name" error={errors.name ? 'Enter the name to greet you by.' : undefined}>
            <Input name="name" autoComplete="name" defaultValue={old('name')} maxLength={100} required />
          </Field>
          <EmailField error={errors.email} old={old('email')} />
          <Submit busy={processing}>{processing ? 'Sending…' : 'Email me a link'}</Submit>
        </>
      )}
    </AccountForm>
  );
}

export function CompleteSignUpForm({ token, minLength }: { token: string; minLength: number }) {
  return (
    <AccountForm action={completeSignUp}>
      {({ errors, processing }) => (
        <>
          <input type="hidden" name="token" value={token} />
          <NewPasswordField error={errors.password} minLength={minLength} label="Choose a password" />
          <Submit busy={processing}>{processing ? 'Making your account…' : 'Make my account'}</Submit>
        </>
      )}
    </AccountForm>
  );
}

export function SignInForm({ next }: { next: string }) {
  return (
    <AccountForm action={signIn}>
      {({ errors, processing, old }) => (
        <>
          <input type="hidden" name="next" value={next} />
          <EmailField error={errors.email} old={old('email')} />
          <Field label="Password" error={errors.password}>
            <Input type="password" name="password" autoComplete="current-password" required />
          </Field>
          <Submit busy={processing}>{processing ? 'Signing in…' : 'Sign in'}</Submit>
        </>
      )}
    </AccountForm>
  );
}

/**
 * Signs in with a passkey: the server's challenge, bound to this session,
 * signed by whichever passkey the browser offers for this host. Shown only
 * where the browser has passkeys at all.
 */
export function PasskeySignIn({ next }: { next: string }) {
  const [busy, setBusy] = useState(false);
  const [failure, setFailure] = useState<string | null>(null);
  // Known only in the browser: nothing on the server and on the first
  // render, so hydration finds what the server sent.
  const [supported, setSupported] = useState(false);
  useEffect(() => setSupported(passkeysSupported()), []);
  if (!supported) return null;

  const go = async () => {
    setBusy(true);
    setFailure(null);
    try {
      const options = (await passkeySignInOptions({})).value as Record<string, unknown>;
      const credential = await signWithPasskey(options);
      const result = await signInWithPasskey({ credential, next });
      const to = (result.value as { next?: unknown } | null)?.next;
      window.location.assign(typeof to === 'string' ? to : '/');
    } catch (error) {
      setBusy(false);
      setFailure(messageOf(error, "The passkey didn't sign you in. Try again, or use your password."));
    }
  };

  return (
    <div {...stylex.props(styles.form)}>
      {failure && (
        <Banner tone="bad" title="Not signed in">
          {failure}
        </Banner>
      )}
      <Button type="button" size="lg" disabled={busy} style={styles.full} onClick={() => void go()}>
        {busy ? 'Waiting for your passkey…' : 'Sign in with a passkey'}
      </Button>
    </div>
  );
}

/** The server's own words where it sent some -- a field error -- or $fallback. */
export function messageOf(error: unknown, fallback: string): string {
  const errors = (error as { errors?: Record<string, string[] | string> } | null)?.errors;
  const first = errors ? Object.values(errors)[0] : undefined;
  if (Array.isArray(first) && typeof first[0] === 'string') return first[0];
  if (typeof first === 'string') return first;
  if ((error as { name?: string } | null)?.name === 'NotAllowedError')
    return 'The passkey prompt was closed before it finished. Try again when you are ready.';
  return fallback;
}

export function SecondFactorForm({ next }: { next: string }) {
  return (
    <AccountForm action={completeSecondFactor}>
      {({ errors, processing }) => (
        <>
          <input type="hidden" name="next" value={next} />
          <Field
            label="Code"
            hint="The six digits your authenticator app shows, or one of your recovery codes."
            error={errors.code}
          >
            <Input name="code" autoComplete="one-time-code" inputMode="text" autoFocus required />
          </Field>
          <Submit busy={processing}>{processing ? 'Checking…' : 'Continue'}</Submit>
        </>
      )}
    </AccountForm>
  );
}

export function ForgotPasswordForm() {
  return (
    <AccountForm action={requestPasswordReset}>
      {({ errors, processing, old }) => (
        <>
          <EmailField error={errors.email} old={old('email')} />
          <Submit busy={processing}>{processing ? 'Sending…' : 'Email me a reset link'}</Submit>
        </>
      )}
    </AccountForm>
  );
}

export function ResetPasswordForm({ token, minLength }: { token: string; minLength: number }) {
  return (
    <AccountForm action={resetPassword}>
      {({ errors, processing }) => (
        <>
          <input type="hidden" name="token" value={token} />
          <NewPasswordField error={errors.password} minLength={minLength} />
          <Submit busy={processing}>{processing ? 'Saving…' : 'Set the new password'}</Submit>
        </>
      )}
    </AccountForm>
  );
}

export function ConfirmEmailForm({ token }: { token: string }) {
  return (
    <AccountForm action={confirmEmailChange}>
      {({ processing }) => (
        <>
          <input type="hidden" name="token" value={token} />
          <Submit busy={processing}>{processing ? 'Moving…' : 'Move my account to this address'}</Submit>
        </>
      )}
    </AccountForm>
  );
}

export function ChangePasswordForm({ minLength }: { minLength: number }) {
  return (
    <AccountForm action={changePassword}>
      {({ errors, processing }) => (
        <>
          <Field label="Current password" error={errors.currentPassword}>
            <Input type="password" name="currentPassword" autoComplete="current-password" required />
          </Field>
          <NewPasswordField error={errors.password} minLength={minLength} />
          <Submit busy={processing}>{processing ? 'Saving…' : 'Change password'}</Submit>
        </>
      )}
    </AccountForm>
  );
}

export function ChangeEmailForm() {
  return (
    <AccountForm action={requestEmailChange}>
      {({ errors, processing, old }) => (
        <>
          <Field label="New email" error={errors.email}>
            <Input
              type="email"
              name="email"
              autoComplete="email"
              inputMode="email"
              defaultValue={old('email')}
              required
            />
          </Field>
          <Field label="Current password" error={errors.currentPassword}>
            <Input type="password" name="currentPassword" autoComplete="current-password" required />
          </Field>
          <Submit busy={processing}>{processing ? 'Sending…' : 'Send a link to the new address'}</Submit>
        </>
      )}
    </AccountForm>
  );
}

const styles = stylex.create({
  form: { display: 'flex', flexDirection: 'column', rowGap: space.s4 },
  full: { width: '100%' },
});
