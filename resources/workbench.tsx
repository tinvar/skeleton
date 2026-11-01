import type { Workbench } from '@tinvar/renderer/workbench';
import { renderToReadableStream } from '@vitejs/plugin-rsc/rsc/server';
import type { ReactNode } from 'react';
import * as stylex from '@stylexjs/stylex';
import { global } from '@meridian/ui/tokens';
import { runInRenderContext } from '@tinvar/react/server';
import { FormSession } from '@tinvar/react/form';
import { HeadProvider } from '@tinvar/react/head';
import { renderRsc, type RenderFrame } from './entries/rsc';
import './styles/app.css';
import { NoteList } from './components/NoteList';

/**
 * The app's pages and components, drawn in isolation by the dev server's
 * workbench (/_vite/__workbench) and playground (/_vite/__playground), from
 * the samples here -- in dev alone, and only where TINVAR_ENV says local,
 * development or testing. No entry imports this module, so no build has it.
 *
 * A sample is invented: the data a page's loader would return, and the
 * answer to each query its components call. Add a sample beside each page
 * and component you want to look at on its own.
 */

const notes = [
  { id: 'n-1', body: 'Ship the first page.', createdAt: '2026-10-28T09:30:00.000Z' },
  { id: 'n-2', body: 'Write a query and a test for it.', createdAt: '2026-10-28T10:05:00.000Z' },
];

const workbench: Workbench = {
  pages: {
    home: {
      route: 'app/home',
      samples: {
        'with-notes': {
          page: { appName: 'Tinvar app', limit: 10 },
          queries: { recentNotes: { notes, total: 2 } },
        },
        empty: {
          page: { appName: 'Tinvar app', limit: 10 },
          queries: { recentNotes: { notes: [], total: 0 } },
        },
      },
    },
  },
  components: {
    NoteList: {
      render: (props: { limit: number }) => <NoteList limit={props.limit} />,
      queries: { recentNotes: { notes, total: 12 } },
      samples: { 'some-of-many': { props: { limit: 2 } } },
    },
  },

  // A page as the renderer draws it; resources/workbench.ssr.tsx makes the
  // HTML of it.
  async renderPage(frame, client, mints) {
    return renderRsc({ ...frame } as RenderFrame, client, mints);
  },

  // One component in a document with the app's styles and what its forms
  // and head tags read.
  async renderElement(element, frame, client, mints) {
    return runInRenderContext(
      { requestId: frame.rid, nonce: frame.nonce, allowed: frame.allow, client, mints },
      () => renderToReadableStream(<Frame>{element}</Frame>),
    );
  },
};

function Frame({ children }: { children: ReactNode }) {
  return (
    <html lang="en" {...stylex.props(global.html)}>
      <head>
        <meta charSet="utf-8" />
        <meta name="color-scheme" content="light dark" />
        {import.meta.viteRsc.loadCss()}
      </head>
      <body {...stylex.props(global.body)}>
        <FormSession csrfToken={null} flash={null}>
          <HeadProvider defaultTitle="Workbench">{children}</HeadProvider>
        </FormSession>
      </body>
    </html>
  );
}

export default workbench;
