import { createFromReadableStream, getClientEntryUrl } from '@vitejs/plugin-rsc/ssr';
import { renderToReadableStream } from 'react-dom/server.edge';
import clientReferences from 'virtual:vite-rsc/client-references';
import { warmClientReferences } from '@tinvar/renderer/warm';

/**
 * RSC stream -> React tree -> HTML, with the client entry as a bootstrap module.
 *
 * `nonce` is the per-request CSP nonce PHP minted. React writes it on every
 * script it emits -- the bootstrap module and the inline Suspense reveals --
 * and PHP's Content-Security-Policy allows only scripts that carry it, so a
 * render without it is a page whose hydration the browser refuses to run.
 */
export async function handleSsr(
  rscStream: ReadableStream,
  nonce: string,
  onError?: (error: unknown) => string | void,
): Promise<ReadableStream> {
  const root = await createFromReadableStream<React.ReactNode>(rscStream);

  return renderToReadableStream(root, {
    nonce,
    bootstrapModules: [getClientEntryUrl()],
    // The digest PHP logged the failure under, which React hands the
    // browser with the boundary it could not render here.
    onError(error) {
      return onError?.(error);
    },
  });
}

/**
 * Every client component's module, loaded before the first render: the
 * renderer awaits it through the rsc entry's warm() before it takes a page,
 * so the first page's head preloads every chunk a later one's would.
 */
export function warm() {
  return warmClientReferences(clientReferences);
}
