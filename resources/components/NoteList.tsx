import * as stylex from '@stylexjs/stylex';
import { Text } from '@meridian/ui';
import { colors, space } from '@meridian/ui/tokens.stylex';
import { recentNotes } from '@queries';

/**
 * The newest notes: a server component, awaiting App\Queries\RecentNotes
 * over the render's own socket. The build reads this call, and the home
 * route may call this query and no other.
 */
export async function NoteList({ limit }: { limit: number }) {
  const { notes, total } = await recentNotes({ limit });

  if (notes.length === 0) {
    return <Text tone="secondary">No notes yet. Add the first one above.</Text>;
  }

  return (
    <section {...stylex.props(styles.section)} aria-label="Notes">
      <Text size="sm" tone="secondary">
        {total === notes.length
          ? `${total} ${total === 1 ? 'note' : 'notes'}`
          : `The newest ${notes.length} of ${total} notes`}
      </Text>
      <ul {...stylex.props(styles.list)}>
        {notes.map((note) => (
          <li key={note.id} {...stylex.props(styles.item)}>
            <Text as="p" style={styles.body}>
              {note.body}
            </Text>
            <Text size="sm" tone="secondary" mono>
              <time dateTime={note.createdAt}>{`${note.createdAt.slice(0, 16).replace('T', ' ')} UTC`}</time>
            </Text>
          </li>
        ))}
      </ul>
    </section>
  );
}

const styles = stylex.create({
  section: { display: 'flex', flexDirection: 'column', rowGap: space.s3 },
  list: { display: 'flex', flexDirection: 'column', listStyle: 'none', margin: 0, padding: 0 },
  item: {
    borderBlockStartColor: colors.border,
    borderBlockStartStyle: 'solid',
    borderBlockStartWidth: '1px',
    display: 'flex',
    flexDirection: 'column',
    paddingBlock: space.s4,
    rowGap: space.s1,
  },
  body: { margin: 0, overflowWrap: 'anywhere', whiteSpace: 'pre-wrap' },
});
