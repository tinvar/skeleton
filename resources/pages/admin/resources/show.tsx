import * as stylex from '@stylexjs/stylex';
import { DescriptionList, Heading } from '@meridian/ui';
import { space } from '@meridian/ui/tokens.stylex';
import { Title } from '@tinvar/react/head';
import { Link } from '@tinvar/react/link';
import type { PageProps, ResourceDetailData } from '../../../types/generated';
import { displayValue } from '../../../lib/resource';
import { ResourceDeleteButton } from '../../../components/admin/ResourceDeleteButton';

/**
 * One record of an admin resource (ADR 0152): its lines, then the sections
 * the resource adds under them, with edit and delete where the reader's
 * policy said yes.
 */
export default function Page({ data }: PageProps<'admin/resources/show'>) {
  const { resource, title, details, sections, editUrl, canDelete, actions, id } = data;

  return (
    <div {...stylex.props(styles.page)}>
      <Title>{`${title} · ${resource.plural}`}</Title>
      <Link href={resource.indexUrl}>{`All ${resource.plural.toLowerCase()}`}</Link>
      <header {...stylex.props(styles.header)}>
        <Heading level={1} size="title1">
          {title}
        </Heading>
        <div {...stylex.props(styles.actions)}>
          {editUrl === null ? null : <Link href={editUrl}>Edit</Link>}
          {canDelete ? <ResourceDeleteButton action={actions.destroy} id={id} title={title} /> : null}
        </div>
      </header>
      <DescriptionList items={details.map(item)} />
      {sections.map((section) => (
        <section key={section.title} {...stylex.props(styles.page)}>
          <Heading level={2} size="title3">
            {section.title}
          </Heading>
          <DescriptionList items={section.details.map(item)} />
        </section>
      ))}
    </div>
  );
}

function item(detail: ResourceDetailData) {
  return { term: detail.label, value: displayValue(detail.kind, detail.value, detail.options) };
}

const styles = stylex.create({
  page: { display: 'flex', flexDirection: 'column', rowGap: space.s6 },
  header: { alignItems: 'center', display: 'flex', justifyContent: 'space-between' },
  actions: { alignItems: 'center', columnGap: space.s4, display: 'flex' },
});
