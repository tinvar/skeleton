import type { Workbench } from '@tinvar/renderer/workbench';
import { renderToReadableStream } from '@vitejs/plugin-rsc/rsc/server';
import { runInRenderContext } from '@tinvar/react/server';
import { Document, renderRsc, type RenderFrame } from './entries/rsc';
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
  { id: 'n-1', body: 'Ship the first page.', createdAt: '2026-10-28T09:30:00Z' },
  { id: 'n-2', body: 'Write a query and a test for it.', createdAt: '2026-10-28T10:05:00Z' },
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

  // Whole, every boundary filled in: the frame runs no script.
  async renderPage(frame, client, mints) {
    const ssr = await import.meta.viteRsc.loadModule<typeof import('./entries/ssr')>('ssr', 'index');
    return ssr.prerenderHtml(renderRsc({ ...frame } as RenderFrame, client, mints));
  },

  async renderElement(element, frame, client, mints) {
    const document: RenderFrame = { ...frame, route: 'workbench', params: {}, data: { page: null } };
    const rsc = runInRenderContext(
      { requestId: frame.rid, nonce: frame.nonce, allowed: frame.allow, client, mints },
      () => renderToReadableStream(<Document frame={document}>{element}</Document>),
    );
    const ssr = await import.meta.viteRsc.loadModule<typeof import('./entries/ssr')>('ssr', 'index');
    return ssr.prerenderHtml(rsc);
  },
};

export default workbench;
