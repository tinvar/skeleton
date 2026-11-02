'use client';

import { useState } from 'react';
import * as stylex from '@stylexjs/stylex';
import { Banner, Button, Checkbox, CodeBlock, Field, Input, Select } from '@meridian/ui';
import { space } from '@meridian/ui/tokens.stylex';
import { Form, type HttpFailure } from '@tinvar/react/form';
import { create as createApiToken, revoke as revokeApiToken } from '@actions/ApiTokenActions';
import { describe } from './AccountForms';

/*
 * The account's API tokens (ADR 0159): issuing one, which answers with its
 * text once, and revoking one.
 */

type Choice = { value: string; label: string };

type Failure = { title: string; detail: string };

/** What the create action answers with: the token's text, shown here once and kept nowhere. */
type Issued = { id: string; name: string; token: string };

function isIssued(value: unknown): value is Issued {
  return (
    typeof value === 'object' &&
    value !== null &&
    typeof (value as Issued).token === 'string' &&
    typeof (value as Issued).name === 'string'
  );
}

/** Issue a token: a name, its scopes, how long it lasts. */
export function CreateApiTokenForm({ scopes, lifetimes }: { scopes: Choice[]; lifetimes: Choice[] }) {
  const [failure, setFailure] = useState<Failure | null>(null);
  const [issued, setIssued] = useState<Issued | null>(null);

  return (
    <div {...stylex.props(styles.stack)}>
      {issued && (
        <Banner tone="good" title={`Copy “${issued.name}” now`}>
          <div {...stylex.props(styles.stack)}>
            <span>
              It is shown this once: the app keeps only a digest of it, so nothing can show it again.
            </span>
            <CodeBlock code={issued.token} title="API token" wrap />
          </div>
        </Banner>
      )}
      <Form
        noValidate
        resetOnSuccess
        onStart={() => {
          setFailure(null);
          setIssued(null);
        }}
        onSuccess={(result) => setIssued(isIssued(result.value) ? result.value : null)}
        onHttpException={(f: HttpFailure) => setFailure(describe(f))}
        onNetworkError={() => setFailure(describe(null))}
        {...stylex.props(styles.stack)}
        action={createApiToken}
      >
        {(slot) => (
          <>
            {failure && (
              <Banner tone="bad" title={failure.title}>
                {failure.detail}
              </Banner>
            )}
            <Field
              label="Name"
              hint="What the token is for, so you know which to revoke."
              error={slot.errors.name}
            >
              <Input name="name" autoComplete="off" placeholder="Nightly notes export" maxLength={100} />
            </Field>
            <fieldset {...stylex.props(styles.fieldset)}>
              <legend>Scopes</legend>
              {scopes.map((scope) => (
                <Checkbox
                  key={scope.value}
                  name="scopes[]"
                  value={scope.value}
                  label={scope.value}
                  description={scope.label}
                />
              ))}
              {slot.errors.scopes && <span role="alert">{slot.errors.scopes}</span>}
            </fieldset>
            <Field label="Expires" error={slot.errors.lifetime}>
              <Select name="lifetime" defaultValue={lifetimes[0]?.value}>
                {lifetimes.map((lifetime) => (
                  <option key={lifetime.value} value={lifetime.value}>
                    {lifetime.label}
                  </option>
                ))}
              </Select>
            </Field>
            <div>
              <Button type="submit" variant="primary" disabled={slot.processing}>
                {slot.processing ? 'Creating…' : 'Create token'}
              </Button>
            </div>
          </>
        )}
      </Form>
    </div>
  );
}

/** Revoke one token: anything using it is refused from the next call. */
export function RevokeApiTokenButton({ id, name }: { id: string; name: string }) {
  return (
    <Form action={revokeApiToken}>
      {(slot) => (
        <>
          <input type="hidden" name="id" value={id} />
          <Button type="submit" size="sm" disabled={slot.processing} aria-label={`Revoke the token ${name}`}>
            Revoke
          </Button>
        </>
      )}
    </Form>
  );
}

const styles = stylex.create({
  stack: { display: 'flex', flexDirection: 'column', rowGap: space.s3 },
  fieldset: {
    border: 'none',
    display: 'flex',
    flexDirection: 'column',
    margin: 0,
    padding: 0,
    rowGap: space.s2,
  },
});
