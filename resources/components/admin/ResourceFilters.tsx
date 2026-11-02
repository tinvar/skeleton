'use client';

import { useState, type FormEvent } from 'react';
import * as stylex from '@stylexjs/stylex';
import { Button, Field, Input, Select } from '@meridian/ui';
import { space } from '@meridian/ui/tokens.stylex';
import { useRouter } from '@tinvar/react/link';
import type { ResourceIndexData } from '../../types/generated';
import { listUrl } from '../../lib/resource';

type List = Pick<
  ResourceIndexData,
  'resource' | 'filters' | 'search' | 'searchable' | 'sort' | 'direction' | 'page'
>;

/**
 * An admin list's search box and filters (ADR 0152): a real GET form for
 * the list's own URL, so it filters before script loads, and with script a
 * visit to the same URL. What it sends is a request: the server keeps only
 * a filter value it offered, and searches only what the resource says it
 * searches.
 */
export function ResourceFilters({ list }: { list: List }) {
  const router = useOptionalRouter();
  const [search, setSearch] = useState(list.search);
  const [chosen, setChosen] = useState<Record<string, string | null>>(
    Object.fromEntries(list.filters.map((filter) => [filter.key, filter.value])),
  );

  if (!list.searchable && list.filters.length === 0) return null;

  const submit = (event: FormEvent) => {
    event.preventDefault();
    const href = listUrl(list, { search, filters: chosen });
    if (router) void router.visit(href);
    else window.location.assign(href);
  };

  return (
    <form
      method="get"
      action={list.resource.indexUrl}
      onSubmit={submit}
      role="search"
      {...stylex.props(styles.form)}
    >
      {list.searchable ? (
        <Field label={`Search ${list.resource.plural.toLowerCase()}`}>
          <Input
            type="search"
            name="search"
            value={search}
            onChange={(event) => setSearch(event.target.value)}
          />
        </Field>
      ) : null}
      {list.filters.map((filter) => (
        <Field key={filter.key} label={filter.label}>
          <Select
            name={filter.key}
            value={chosen[filter.key] ?? ''}
            onChange={(event) =>
              setChosen({ ...chosen, [filter.key]: event.target.value === '' ? null : event.target.value })
            }
          >
            <option value="">Any</option>
            {filter.options.map((option) => (
              <option key={option.value} value={option.value}>
                {option.label}
              </option>
            ))}
          </Select>
        </Field>
      ))}
      {list.sort !== null ? (
        <>
          <input type="hidden" name="sort" value={list.sort} />
          <input type="hidden" name="direction" value={list.direction} />
        </>
      ) : null}
      <Button type="submit" variant="secondary">
        Apply
      </Button>
    </form>
  );
}

/**
 * The router, or null where there is none -- the server's render of this
 * component, which has no RouterProvider above it, and where useRouter()
 * throws by design.
 */
function useOptionalRouter(): ReturnType<typeof useRouter> | null {
  try {
    return useRouter();
  } catch {
    return null;
  }
}

const styles = stylex.create({
  form: { alignItems: 'flex-end', columnGap: space.s3, display: 'flex', flexWrap: 'wrap', rowGap: space.s3 },
});
