import * as stylex from '@stylexjs/stylex';
import { Heading, Skeleton, Text } from '@meridian/ui';
import { space } from '@meridian/ui/tokens.stylex';
import { Suspense } from 'react';
import { Title } from '@tinvar/react/head';
import type { PageProps } from '../../types/generated';
import { NoteForm } from '../../components/NoteForm';
import { NoteList } from '../../components/NoteList';

/**
 * The home page, a server component. `data` is what App\Pages\Home's
 * loader returned, typed from the PHP by codegen. The list streams in
 * behind its own boundary when its query resolves; the form is a client
 * component, which posts to an action.
 */
export default function Page({ data }: PageProps<'app/home'>) {
  return (
    <div {...stylex.props(styles.page)}>
      <Title>Notes</Title>
      <header {...stylex.props(styles.header)}>
        <Heading level={1} size="title1">
          Notes
        </Heading>
        <Text tone="secondary">
          {`${data.appName} is running. Leave a note: it is saved by an action, and listed by a query.`}
        </Text>
      </header>
      <NoteForm />
      <Suspense fallback={<Skeleton width="100%" height="96px" />}>
        <NoteList limit={data.limit} />
      </Suspense>
    </div>
  );
}

const styles = stylex.create({
  page: { display: 'flex', flexDirection: 'column', maxWidth: '640px', rowGap: space.s8 },
  header: { display: 'flex', flexDirection: 'column', rowGap: space.s2 },
});
