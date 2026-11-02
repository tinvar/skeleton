import * as stylex from '@stylexjs/stylex';
import { EmptyState, Heading, Table, TBody, Td, Text, Th, THead, Tr } from '@meridian/ui';
import { space } from '@meridian/ui/tokens.stylex';
import { Title } from '@tinvar/react/head';
import { Link } from '@tinvar/react/link';
import type { PageProps } from '../../../types/generated';
import { displayValue, isNumeric, listUrl } from '../../../lib/resource';
import { ResourceFilters } from '../../../components/admin/ResourceFilters';
import { ResourceBulkForm } from '../../../components/admin/ResourceBulkForm';

/**
 * Every admin resource's list (ADR 0152), drawn from what the server says
 * the resource is: its columns, a page of its rows, its filters, search and
 * bulk actions. One view for every resource in the admin panel, registered
 * once in resources/entries/rsc.tsx.
 *
 * A sortable column's heading is a link that sorts by it, and the pages
 * under the table are links too, so the list sorts, filters and pages
 * before any script loads. Edit and create are drawn only where the
 * reader's policy said yes; each screen and action asks it again.
 */
export default function Page({ data }: PageProps<'admin/resources/index'>) {
  const { resource, columns, rows, total, page, lastPage, bulkActions, actions } = data;
  const filtered = data.search !== '' || data.filters.some((filter) => filter.value !== null);
  const noun = total === 1 ? resource.singular.toLowerCase() : resource.plural.toLowerCase();

  return (
    <div {...stylex.props(styles.page)}>
      <Title>{resource.plural}</Title>
      <header {...stylex.props(styles.header)}>
        <div>
          <Heading level={1} size="title1">
            {resource.plural}
          </Heading>
          <Text size="sm" tone="secondary">{`${total} ${noun}${filtered ? ' match' : ''}.`}</Text>
        </div>
        {resource.createUrl === null ? null : (
          <Link href={resource.createUrl}>New {resource.singular.toLowerCase()}</Link>
        )}
      </header>
      <ResourceFilters list={data} />

      {rows.length === 0 ? (
        <EmptyState
          title={
            filtered ? `No ${resource.plural.toLowerCase()} match` : `No ${resource.plural.toLowerCase()} yet`
          }
          description={filtered ? 'Clear the search or a filter to see more.' : undefined}
        />
      ) : (
        <ResourceBulkForm action={actions.bulk} actions={bulkActions} plural={resource.plural}>
          <Table
            label={resource.plural}
            footer={
              lastPage > 1 ? (
                <nav aria-label={`Pages of ${resource.plural.toLowerCase()}`} {...stylex.props(styles.pager)}>
                  {page > 1 ? <Link href={listUrl(data, { page: page - 1 })}>Previous</Link> : <span />}
                  <Text size="sm" tone="secondary">{`Page ${page} of ${lastPage}`}</Text>
                  {page < lastPage ? <Link href={listUrl(data, { page: page + 1 })}>Next</Link> : <span />}
                </nav>
              ) : undefined
            }
          >
            <THead>
              <Tr>
                {bulkActions.length > 0 ? (
                  <Th width="2.5rem">
                    <span {...stylex.props(styles.hidden)}>Ticked</span>
                  </Th>
                ) : null}
                {columns.map((column) => {
                  const sorted = data.sort === column.key ? data.direction : null;
                  const next = sorted === 'asc' ? 'desc' : 'asc';
                  return (
                    <Th
                      key={column.key}
                      numeric={isNumeric(column.kind)}
                      aria-sort={sorted === null ? undefined : sorted === 'asc' ? 'ascending' : 'descending'}
                    >
                      {column.sortable ? (
                        <Link href={listUrl(data, { sort: column.key, direction: next })}>
                          {column.label}
                          {sorted === null ? '' : sorted === 'asc' ? ' ↑' : ' ↓'}
                        </Link>
                      ) : (
                        column.label
                      )}
                    </Th>
                  );
                })}
                <Th>
                  <span {...stylex.props(styles.hidden)}>Actions</span>
                </Th>
              </Tr>
            </THead>
            <TBody>
              {rows.map((row) => (
                <Tr key={row.id}>
                  {bulkActions.length > 0 ? (
                    <Td>
                      <input type="checkbox" name="ids[]" value={row.id} aria-label={`Tick ${row.title}`} />
                    </Td>
                  ) : null}
                  {columns.map((column, index) => {
                    const value = displayValue(column.kind, row.cells[column.key] ?? null, column.options);
                    return (
                      <Td key={column.key} numeric={isNumeric(column.kind)}>
                        {index === 0 ? <Link href={row.showUrl}>{value}</Link> : value}
                      </Td>
                    );
                  })}
                  <Td>{row.editUrl === null ? null : <Link href={row.editUrl}>Edit</Link>}</Td>
                </Tr>
              ))}
            </TBody>
          </Table>
        </ResourceBulkForm>
      )}
    </div>
  );
}

const styles = stylex.create({
  page: { display: 'flex', flexDirection: 'column', rowGap: space.s6 },
  header: { alignItems: 'center', display: 'flex', justifyContent: 'space-between' },
  pager: { alignItems: 'center', columnGap: space.s4, display: 'flex', justifyContent: 'space-between' },
  hidden: {
    clip: 'rect(0 0 0 0)',
    height: 1,
    overflow: 'hidden',
    position: 'absolute',
    whiteSpace: 'nowrap',
    width: 1,
  },
});
