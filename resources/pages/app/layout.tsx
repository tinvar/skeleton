import * as stylex from '@stylexjs/stylex';
import { AppHeader, Container, LogoMark, Text } from '@meridian/ui';
import { colors, space } from '@meridian/ui/tokens.stylex';
import { media } from '@meridian/ui/palette.stylex';
import type { ReactNode } from 'react';
import { Link } from '@tinvar/react/link';

/**
 * The shell around every page: the header, the page, and a footer.
 * App\Pages\Layout is its PHP side, at `/`, so it wraps every route.
 */
export default function AppLayout({ children }: { children: ReactNode }) {
  return (
    <>
      <AppHeader
        logo={
          <Link href="/" aria-label="Home">
            <LogoMark size={24} />
          </Link>
        }
        breadcrumbs={<Text weight={600}>Tinvar app</Text>}
      />
      <main {...stylex.props(styles.main)}>
        <Container>{children}</Container>
      </main>
      <footer {...stylex.props(styles.footer)}>
        <Container>
          <Text size="sm" tone="secondary">
            Made with Tinvar. Edit resources/pages/app/home.tsx to change this page.
          </Text>
        </Container>
      </footer>
    </>
  );
}

const styles = stylex.create({
  main: {
    minHeight: '70dvh',
    paddingBlock: { default: space.s8, [media.phone]: space.s6 },
  },
  footer: {
    borderBlockStartColor: colors.border,
    borderBlockStartStyle: 'solid',
    borderBlockStartWidth: '1px',
    paddingBlock: space.s6,
  },
});
