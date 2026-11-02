// The admin panel's shared helpers (ADR 0152): drawing a value by the kind
// the server says it is, and the list's links. Pure functions of their
// arguments, for server and client components alike.

import type { ColumnKind, Direction, ResourceIndexData, ResourceOptionData } from '../types/generated';

const moment = new Intl.DateTimeFormat('en-GB', { dateStyle: 'medium', timeStyle: 'short', timeZone: 'UTC' });
const cents = new Intl.NumberFormat('en-GB', { style: 'currency', currency: 'USD' });

/**
 * A value as text, by its kind: money from cents, a number with its
 * separators, an enum by its case's label, a moment in UTC, a bool as Yes or
 * No. A value crosses as text whatever it is (ResourceRowData); null is a
 * dash.
 */
export function displayValue(
  kind: ColumnKind,
  value: string | null,
  options: ResourceOptionData[] = [],
): string {
  if (value === null) return '—';
  switch (kind) {
    case 'money':
      return cents.format(Number(value) / 100);
    case 'number':
      return Number(value).toLocaleString('en-GB');
    case 'enum':
      return options.find((option) => option.value === value)?.label ?? value;
    case 'moment':
      return `${moment.format(new Date(value))} UTC`;
    case 'boolean':
      return value === 'true' ? 'Yes' : 'No';
    default:
      return value;
  }
}

/** Whether a column's values line up on the right, as numbers do. */
export function isNumeric(kind: ColumnKind): boolean {
  return kind === 'money' || kind === 'number';
}

/**
 * The list's URL with some of what it was asked for changed: its filters,
 * search, sort and page, each left out when it is the default. A change to
 * any but the page starts again at the first page.
 */
export function listUrl(
  list: Pick<ResourceIndexData, 'resource' | 'filters' | 'search' | 'sort' | 'direction' | 'page'>,
  change: {
    sort?: string | null;
    direction?: Direction;
    page?: number;
    search?: string;
    filters?: Record<string, string | null>;
  },
): string {
  const query = new URLSearchParams();
  for (const filter of list.filters) {
    const value = change.filters && filter.key in change.filters ? change.filters[filter.key] : filter.value;
    if (value !== null && value !== undefined && value !== '') query.set(filter.key, value);
  }
  const search = change.search ?? list.search;
  if (search !== '') query.set('search', search);
  const sort = change.sort === undefined ? list.sort : change.sort;
  if (sort !== null) {
    query.set('sort', sort);
    query.set('direction', change.direction ?? list.direction);
  }
  const page = change.page ?? (Object.keys(change).length === 0 ? list.page : 1);
  if (page > 1) query.set('page', String(page));
  const rendered = query.toString();

  return rendered === '' ? list.resource.indexUrl : `${list.resource.indexUrl}?${rendered}`;
}
