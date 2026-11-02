'use client';

import { useState, type ReactNode } from 'react';
import * as stylex from '@stylexjs/stylex';
import { Banner, Button, Field, Select } from '@meridian/ui';
import { space } from '@meridian/ui/tokens.stylex';
import { Form, type HttpFailure } from '@tinvar/react/form';
import type { ResourceBulkActionData } from '../../types/generated';
import { describe } from '../account/AccountForms';

type Failure = { title: string; detail: string };

/**
 * An admin list's table inside the form its bulk actions post (ADR 0152):
 * each row's checkbox is an `ids[]` field of it, so the table stays the
 * server's and the form reads the ticks from the page. Posts to the
 * resource's bulk action, which asks each row's policy and changes all of
 * them or none; the list renders again in place after. A destructive one
 * is asked about first.
 */
export function ResourceBulkForm({
  action,
  actions,
  plural,
  children,
}: {
  action: string;
  actions: ResourceBulkActionData[];
  plural: string;
  children: ReactNode;
}) {
  const [failure, setFailure] = useState<Failure | null>(null);
  const [chosen, setChosen] = useState(actions[0]?.key ?? '');

  if (actions.length === 0) return <>{children}</>;

  const confirmed = (data: Record<string, unknown>): boolean => {
    const picked = actions.find((candidate) => candidate.key === data.action);
    const ids = Array.isArray(data.ids) ? data.ids.length : 0;
    if (ids === 0) {
      setFailure({ title: 'Nothing ticked', detail: `Tick the ${plural.toLowerCase()} to change first.` });
      return false;
    }
    return (
      !picked?.destructive ||
      window.confirm(`${picked.label}: ${ids} ${plural.toLowerCase()}? It cannot be undone.`)
    );
  };

  return (
    <Form
      onStart={() => setFailure(null)}
      onBefore={confirmed}
      onError={(errors) =>
        setFailure({
          title: 'Nothing was changed',
          detail: Object.values(errors).flat()[0] ?? 'Load the list again and try once more.',
        })
      }
      onHttpException={(f: HttpFailure) =>
        setFailure(
          f.status === 403
            ? {
                title: 'Nothing was changed',
                detail: 'You may not do that to every row ticked, so none was changed.',
              }
            : describe(f),
        )
      }
      onNetworkError={() => setFailure(describe(null))}
      {...stylex.props(styles.form)}
      action={action}
    >
      {failure && (
        <Banner tone="bad" title={failure.title}>
          {failure.detail}
        </Banner>
      )}
      <div {...stylex.props(styles.bar)}>
        <Field label="With the ticked rows">
          <Select name="action" value={chosen} onChange={(event) => setChosen(event.target.value)}>
            {actions.map((candidate) => (
              <option key={candidate.key} value={candidate.key}>
                {candidate.label}
              </option>
            ))}
          </Select>
        </Field>
        <Button type="submit" variant="secondary">
          Run
        </Button>
      </div>
      {children}
    </Form>
  );
}

const styles = stylex.create({
  form: { display: 'flex', flexDirection: 'column', rowGap: space.s3 },
  bar: { alignItems: 'flex-end', columnGap: space.s3, display: 'flex', flexWrap: 'wrap', rowGap: space.s2 },
});
