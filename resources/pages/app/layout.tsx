import * as stylex from '@stylexjs/stylex';
import { AppHeader, Container, LogoMark, Text } from '@meridian/ui';
import { colors, space } from '@meridian/ui/tokens.stylex';
import { media } from '@meridian/ui/palette.stylex';
import { Link } from '@tinvar/react/link';
import type { LayoutProps } from '../../types/generated';
import { SignOutButton } from '../../components/account/AccountSettings';

/**
 * The shell around every page: the header, the page, and a footer.
 * App\Http\Pages\Layout is its PHP side, at `/`, so it wraps every route;
 * its loader says who is signed in, for the header's account links.
 */
export default function AppLayout({ data, children }: LayoutProps<'app'>) {
  return (
    <>
      <AppHeader
        logo={
          <Link href="/" aria-label="Home">
            <LogoMark size={24} />
          </Link>
        }
        breadcrumbs={<Text weight={600}>Tinvar app</Text>}
        actions={
          data.userName === null ? (
            <nav {...stylex.props(styles.nav)} aria-label="Account">
              <Link href="/sign-in">Sign in</Link>
              <Link href="/sign-up">Make an account</Link>
            </nav>
          ) : (
            <nav {...stylex.props(styles.nav)} aria-label="Account">
              <Link href="/account">{data.userName}</Link>
              <SignOutButton />
            </nav>
          )
        }
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
  nav: { alignItems: 'center', columnGap: space.s4, display: 'flex' },
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
