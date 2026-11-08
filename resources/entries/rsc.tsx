import type { ReactNode } from 'react';
import * as stylex from '@stylexjs/stylex';
import { global } from '@meridian/ui/tokens';
import { renderToReadableStream } from '@vitejs/plugin-rsc/rsc/server';
import { deferredRegistry, runInRenderContext } from '@tinvar/react/server';
import { composeRoute } from '@tinvar/react/compose-route';
import { FormSession, type Flashed } from '@tinvar/react/form';
import { HeadLink, HeadProvider } from '@tinvar/react/head';
import favicon from '../brand/favicon.svg?raw';
import '../styles/app.css';
import AppLayout from '../pages/app/layout';
import HomePage from '../pages/app/home';
import ErrorPage from '../pages/app/error';
import SignUpPage from '../pages/account/sign-up';
import CompleteSignUpPage from '../pages/account/complete-sign-up';
import SignInPage from '../pages/account/sign-in';
import TwoFactorPage from '../pages/account/two-factor';
import ForgotPasswordPage from '../pages/account/forgot-password';
import ResetPasswordPage from '../pages/account/reset-password';
import ConfirmEmailPage from '../pages/account/confirm-email';
import SettingsPage from '../pages/account/settings';
import ApiTokensPage from '../pages/account/api-tokens';
import AdminResourceIndexPage from '../pages/admin/resources/index';
import AdminResourceShowPage from '../pages/admin/resources/show';
import AdminResourceFormPage from '../pages/admin/resources/form';
import type { LayoutDataMap } from '../types/generated';

type Page = (props: { data: never; params: never }) => ReactNode;

/**
 * Each view PHP may name, with the layouts around it, outermost first: the
 * order PHP runs their loaders. Add a page's view here when you add the
 * page (`php tinvar make:page` says so).
 */
const ROUTES: Record<string, { Page: Page; layouts: readonly 'app'[] }> = {
  'app/home': { Page: HomePage as Page, layouts: ['app'] },
  // The accounts (ADR 0153): App\Http\Pages\Account.
  'account/sign-up': { Page: SignUpPage as Page, layouts: ['app'] },
  'account/complete-sign-up': { Page: CompleteSignUpPage as Page, layouts: ['app'] },
  'account/sign-in': { Page: SignInPage as Page, layouts: ['app'] },
  'account/two-factor': { Page: TwoFactorPage as Page, layouts: ['app'] },
  'account/forgot-password': { Page: ForgotPasswordPage as Page, layouts: ['app'] },
  'account/reset-password': { Page: ResetPasswordPage as Page, layouts: ['app'] },
  'account/confirm-email': { Page: ConfirmEmailPage as Page, layouts: ['app'] },
  'account/settings': { Page: SettingsPage as Page, layouts: ['app'] },
  'account/api-tokens': { Page: ApiTokensPage as Page, layouts: ['app'] },
  // The admin panel's three views, which draw every #[AdminResource] in it
  // (ADR 0152): App\Http\Resources\NoteResource, and each make:resource --panel=admin.
  'admin/resources/index': { Page: AdminResourceIndexPage as Page, layouts: ['app'] },
  'admin/resources/show': { Page: AdminResourceShowPage as Page, layouts: ['app'] },
  'admin/resources/form': { Page: AdminResourceFormPage as Page, layouts: ['app'] },
  // A system page: no header, since the navigation might be what failed.
  'app/error': { Page: ErrorPage as Page, layouts: [] },
};

export type RenderFrame = {
  route: string;
  params: Record<string, string>;
  data: { page: unknown; layouts?: Record<string, unknown> };
  rid: string;
  nonce: string;
  allow: string[];
  /** A partial reload's segments: render those alone. */
  segments?: string[];
  /** The session's CSRF token, for the hidden field a form posts without script. */
  csrf?: string;
  /** What a form posted without script left for this page. */
  flash?: Flashed;
};

type QueryClientLike = {
  call(name: string, args: Record<string, unknown>, options?: { trailing?: boolean }): Promise<unknown>;
};
type MintClientLike = { mint(component: string, props: Record<string, unknown>): Promise<string> };

type DeferredComponent = (props: Record<string, unknown>) => ReactNode | Promise<ReactNode>;

/**
 * The components a <Deferred> boundary may name. A registry rather than a
 * dynamic import: the name travels inside a signed token, and resolving it
 * to a module path would make a signed string into a loader argument. None
 * yet; add one here when a page defers it.
 */
const COMPONENTS = deferredRegistry<Record<string, DeferredComponent>>({});

type FragmentToken = { component: string; props: Record<string, unknown> };
type FragmentFrame = {
  token?: FragmentToken;
  tokens?: FragmentToken[];
  rid: string;
  nonce: string;
  allow: string[];
};

const FAVICON = `data:image/svg+xml,${encodeURIComponent(favicon.trim())}`;

/**
 * The document every page renders into. The stylesheet is linked here: the
 * StyleX plugin appends what it compiled to the CSS this module imports.
 * With no theme forced, Meridian follows the OS between light and dark.
 */
function Document({ children, frame }: { children: ReactNode; frame: RenderFrame }) {
  return (
    <html lang="en" {...stylex.props(global.html)}>
      <head>
        <meta charSet="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <meta name="color-scheme" content="light dark" />
        {import.meta.viteRsc.loadCss()}
      </head>
      <body {...stylex.props(global.body)}>
        <FormSession csrfToken={frame.csrf ?? null} flash={frame.flash ?? null}>
          <HeadProvider titleTemplate="%s · Tinvar app" defaultTitle="Tinvar app">
            <HeadLink rel="icon" type="image/svg+xml" href={FAVICON} />
            {children}
          </HeadProvider>
        </FormSession>
      </body>
    </html>
  );
}

/** The page in its layouts and the document; or, for a partial reload, only the segments it names. */
function buildTree(frame: RenderFrame) {
  const route = ROUTES[frame.route];
  if (!route) throw new Error(`Unknown route '${frame.route}'`);

  return composeRoute({
    segments: frame.segments ?? null,
    document: (children) => <Document frame={frame}>{children}</Document>,
    page: () => <route.Page data={frame.data.page as never} params={frame.params as never} />,
    layouts: route.layouts.map((key) => ({
      key,
      render: (children: ReactNode) => (
        // Who is signed in, from App\Http\Pages\Layout's loader.
        <AppLayout data={(frame.data.layouts?.[key] ?? { userName: null }) as LayoutDataMap['app']}>
          {children}
        </AppLayout>
      ),
    })),
  });
}

/**
 * Serializes the tree to an RSC stream, inside this render's own context.
 * `onError` is the renderer's: a server component that throws is reported
 * to PHP, under the digest it returns.
 */
export function renderRsc(
  frame: RenderFrame,
  client: QueryClientLike,
  mints?: MintClientLike,
  onError?: (error: unknown) => string | void,
  signal?: AbortSignal,
): ReadableStream<Uint8Array> {
  return runInRenderContext(
    { requestId: frame.rid, nonce: frame.nonce, allowed: frame.allow, client, ...(mints ? { mints } : {}) },
    () => renderToReadableStream(buildTree(frame), { onError, ...(signal ? { signal } : {}) }),
  );
}

/** One deferred subtree, or a group's, with no minting inside it: boundaries do not nest. */
export function renderFragment(
  frame: FragmentFrame,
  client: QueryClientLike,
  onError?: (error: unknown) => string | void,
  signal?: AbortSignal,
): ReadableStream<Uint8Array> {
  const element = (token: FragmentToken, key?: number) => {
    // Its own entries only: a name like `constructor` finds nothing here.
    const Component = Object.hasOwn(COMPONENTS, token.component) ? COMPONENTS[token.component] : undefined;
    if (!Component) throw new Error(`Unknown deferred component '${token.component}'.`);
    return <Component key={key} {...token.props} />;
  };
  const root = frame.tokens ? frame.tokens.map(element) : element(frame.token as FragmentToken);

  return runInRenderContext({ requestId: frame.rid, nonce: frame.nonce, allowed: frame.allow, client }, () =>
    renderToReadableStream(root, { onError, ...(signal ? { signal } : {}) }),
  );
}

/** A whole page: the RSC stream, teed into the HTML and the payload that hydrates it. */
export async function renderHtml(
  frame: RenderFrame,
  client: QueryClientLike,
  onError?: (error: unknown) => string | void,
  mints?: MintClientLike,
  signal?: AbortSignal,
) {
  const [forSsr, forPayload] = renderRsc(frame, client, mints, onError, signal).tee();
  const ssr = await import.meta.viteRsc.loadModule<typeof import('./ssr')>('ssr', 'index');
  const htmlStream = await ssr.handleSsr(forSsr, frame.nonce, onError, signal);

  return { htmlStream, rscStream: forPayload };
}

/**
 * What the renderer runs before it takes its first render: the ssr
 * environment, and every client component's module in it (ADR 0109).
 */
export async function warm() {
  const ssr = await import.meta.viteRsc.loadModule<typeof import('./ssr')>('ssr', 'index');

  return ssr.warm();
}

type MailFrame = { template: string; props: Record<string, unknown>; links?: unknown; rid: string };

/**
 * One email, for the queue worker (a `mail` frame): drawn in the ssr
 * environment, which has the react-dom/server @tinvar/mail renders with.
 * The frame's links -- the message's origin -- go with it, and the kit
 * holds every link it draws to them. No query client: a template is
 * handed everything it shows.
 */
export async function renderMail(frame: MailFrame): Promise<{ html: string; text: string }> {
  const ssr = await import.meta.viteRsc.loadModule<typeof import('./ssr')>('ssr', 'index');

  return ssr.renderMail({ template: frame.template, props: frame.props, links: frame.links });
}

// The plugin's convention; unused by the socket path, kept so `vite build`
// treats this as a valid rsc entry.
export default async function handler(): Promise<Response> {
  return new Response('Tinvar renderer: drive this through the socket.', { status: 400 });
}
