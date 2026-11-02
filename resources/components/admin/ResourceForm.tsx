'use client';

import { useState } from 'react';
import * as stylex from '@stylexjs/stylex';
import { Banner, Button, Checkbox, Field, Input, Select, Textarea } from '@meridian/ui';
import { space } from '@meridian/ui/tokens.stylex';
import { Form, type HttpFailure } from '@tinvar/react/form';
import { Link } from '@tinvar/react/link';
import type { ResourceFormData, ResourceFormFieldData } from '../../types/generated';
import { describe } from '../account/AccountForms';

type Failure = { title: string; detail: string };

/**
 * An admin resource's create or edit form (ADR 0152): one input per field
 * the resource declares, each posting as `values[<name>]`, and the record's
 * id as `record` when editing. A real form, so it saves before script
 * loads; with script, each field's error comes back beside it, under
 * `values.<name>`, and a save goes to the record's page.
 */
export function ResourceForm({ form }: { form: ResourceFormData }) {
  const [failure, setFailure] = useState<Failure | null>(null);

  return (
    <Form
      noValidate
      onStart={() => setFailure(null)}
      onHttpException={(f: HttpFailure) => setFailure(describe(f))}
      onNetworkError={() => setFailure(describe(null))}
      {...stylex.props(styles.form)}
      action={form.action}
    >
      {(slot) => (
        <>
          {failure && (
            <Banner tone="bad" title={failure.title}>
              {failure.detail}
            </Banner>
          )}
          {form.id !== null ? <input type="hidden" name="record" value={form.id} /> : null}
          {form.fields.map((field) => (
            <FieldInput key={field.name} field={field} error={slot.errors[`values.${field.name}`]} />
          ))}
          <div {...stylex.props(styles.actions)}>
            <Button type="submit" variant="primary" disabled={slot.processing}>
              {slot.processing
                ? 'Saving…'
                : form.mode === 'create'
                  ? `Add ${form.resource.singular.toLowerCase()}`
                  : 'Save'}
            </Button>
            <Link href={form.cancelUrl}>Cancel</Link>
          </div>
        </>
      )}
    </Form>
  );
}

function FieldInput({ field, error }: { field: ResourceFormFieldData; error: string | undefined }) {
  const name = `values[${field.name}]`;
  const value = field.value ?? '';

  if (field.kind === 'checkbox') {
    return (
      <Field label={field.label} hint={field.help ?? undefined} error={error}>
        <Checkbox name={name} value="1" defaultChecked={field.value === 'true'} label={field.label} />
      </Field>
    );
  }

  return (
    <Field label={field.label} hint={field.help ?? undefined} error={error} optional={!field.required}>
      {field.kind === 'textarea' ? (
        <Textarea name={name} defaultValue={value} rows={5} maxLength={field.max ?? undefined} />
      ) : field.kind === 'select' ? (
        <Select name={name} defaultValue={value}>
          {field.required && field.value !== null ? null : <option value="">None</option>}
          {field.options.map((option) => (
            <option key={option.value} value={option.value}>
              {option.label}
            </option>
          ))}
        </Select>
      ) : field.kind === 'number' ? (
        <Input
          type="number"
          name={name}
          defaultValue={value}
          min={field.min ?? undefined}
          max={field.max ?? undefined}
          step={field.decimal ? 'any' : 1}
        />
      ) : field.kind === 'datetime' ? (
        <Input type="datetime-local" name={name} defaultValue={value} />
      ) : (
        <Input type="text" name={name} defaultValue={value} maxLength={field.max ?? undefined} />
      )}
    </Field>
  );
}

const styles = stylex.create({
  form: { display: 'flex', flexDirection: 'column', maxWidth: '40rem', rowGap: space.s4 },
  actions: { alignItems: 'center', columnGap: space.s4, display: 'flex' },
});
