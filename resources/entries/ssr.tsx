import { createFromReadableStream, getClientEntryUrl } from '@vitejs/plugin-rsc/ssr';
import { renderToReadableStream } from 'react-dom/server.edge';
import { prerender } from 'react-dom/static.edge';

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
 * RSC stream -> the whole HTML at once, every Suspense boundary filled in
 * and no script: for the dev server's workbench, whose frame runs none, so
 * a boundary streamed in later would stay its fallback.
 */
export async function prerenderHtml(rscStream: ReadableStream): Promise<ReadableStream> {
  const root = await createFromReadableStream<React.ReactNode>(rscStream);
  // However large: React moves a boundary bigger than this out of line, to
  // be put in place by a script.
  const { prelude } = await prerender(root, { progressiveChunkSize: Number.POSITIVE_INFINITY });

  return prelude;
}
