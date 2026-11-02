'use client';

import { useState } from 'react';
import { Banner, Button } from '@meridian/ui';
import { Form, type HttpFailure } from '@tinvar/react/form';
import { describe } from '../account/AccountForms';

type Failure = { title: string; detail: string };

/**
 * Deletes one record of an admin resource (ADR 0152), once the person says
 * yes: its id in a hidden field, posted to the resource's delete action,
 * which asks the record's policy and goes back to the list. Drawn only where
 * the policy said yes; the action asks again.
 */
export function ResourceDeleteButton({ action, id, title }: { action: string; id: string; title: string }) {
  const [failure, setFailure] = useState<Failure | null>(null);

  return (
    <Form
      action={action}
      onStart={() => setFailure(null)}
      onBefore={() => window.confirm(`Delete ${title}? It cannot be undone.`)}
      onHttpException={(f: HttpFailure) => setFailure(describe(f))}
      onNetworkError={() => setFailure(describe(null))}
    >
      {failure && (
        <Banner tone="bad" title={failure.title}>
          {failure.detail}
        </Banner>
      )}
      <input type="hidden" name="record" value={id} />
      <Button type="submit" variant="danger">
        Delete
      </Button>
    </Form>
  );
}
