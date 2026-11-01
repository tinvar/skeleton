import * as stylex from '@stylexjs/stylex';
import { Container, Heading, LogoMark, Text } from '@meridian/ui';
import { colors, fonts, space } from '@meridian/ui/tokens.stylex';
import { Link } from '@tinvar/react/link';
import { Title } from '@tinvar/react/head';
import type { ErrorPageData } from '../../types/generated';

/**
 * A page that could not be shown (App\Http\Pages\ErrorPage): the mark, what
 * happened and why, exactly one way forward, and the reference its failure
 * is logged under. No header: the navigation might be what failed.
 */
export default function Page({ data }: { data: ErrorPageData }) {
  return (
    <main {...stylex.props(styles.main)}>
      <Title>{data.title}</Title>
      <Container reading>
        <section {...stylex.props(styles.panel)} aria-labelledby="error-title">
          <LogoMark size={32} title="Tinvar app" />
          <Text size="caption" tone="secondary" mono>
            {data.eyebrow}
          </Text>
          <div id="error-title">
            <Heading level={1} size="title1">
              {data.title}
            </Heading>
          </div>
          <Text as="p" tone="secondary" style={styles.detail}>
            {data.detail}
          </Text>
          <Link href={data.actionHref}>{data.actionLabel}</Link>
          <Text size="sm" tone="secondary">
            Reference <span {...stylex.props(styles.mono)}>{data.reference}</span>
          </Text>
        </section>
      </Container>
    </main>
  );
}

const styles = stylex.create({
  main: { paddingBlock: space.s16 },
  panel: { alignItems: 'flex-start', display: 'flex', flexDirection: 'column', rowGap: space.s3 },
  detail: { margin: 0 },
  mono: { color: colors.text, fontFamily: fonts.mono, fontSize: '0.92em', overflowWrap: 'anywhere' },
});
