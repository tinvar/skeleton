import { createFromReadableStream } from '@vitejs/plugin-rsc/ssr';
import { prerender } from 'react-dom/static.edge';

/**
 * A workbench render's RSC stream as the whole HTML at once: every Suspense
 * boundary filled in, and no script, for a frame that runs none and so could
 * not fill one in later (ADR 0089). Loaded in the dev server's ssr
 * environment by `workbench()` alone; no entry imports it.
 */
export async function prerenderHtml(
  rscStream: ReadableStream<Uint8Array>,
): Promise<ReadableStream<Uint8Array>> {
  const root = await createFromReadableStream<React.ReactNode>(rscStream);
  // However large: React moves a boundary bigger than this out of line, to
  // be put in place by a script.
  const { prelude } = await prerender(root, { progressiveChunkSize: Number.POSITIVE_INFINITY });

  return prelude;
}
