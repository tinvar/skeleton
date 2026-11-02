import * as stylex from '@stylexjs/stylex';
import { Heading } from '@meridian/ui';
import { space } from '@meridian/ui/tokens.stylex';
import { Title } from '@tinvar/react/head';
import { Link } from '@tinvar/react/link';
import type { PageProps } from '../../../types/generated';
import { ResourceForm } from '../../../components/admin/ResourceForm';

/**
 * An admin resource's create and edit forms (ADR 0152): one view for both,
 * the server saying which, what each field is and what the record holds.
 */
export default function Page({ data }: PageProps<'admin/resources/form'>) {
  return (
    <div {...stylex.props(styles.page)}>
      <Title>{`${data.title} · ${data.resource.plural}`}</Title>
      <Link href={data.resource.indexUrl}>{`All ${data.resource.plural.toLowerCase()}`}</Link>
      <Heading level={1} size="title1">
        {data.mode === 'create' ? data.title : `Edit ${data.title}`}
      </Heading>
      <ResourceForm form={data} />
    </div>
  );
}

const styles = stylex.create({
  page: { display: 'flex', flexDirection: 'column', rowGap: space.s6 },
});
