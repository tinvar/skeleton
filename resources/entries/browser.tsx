import { createFromReadableStream } from '@vitejs/plugin-rsc/browser';
import type { ReactNode } from 'react';
import { hydrateRoot } from 'react-dom/client';
import { createPageRoot, createRouter, inlinePayloadStream } from '@tinvar/react/client';
import { RouterProvider } from '@tinvar/react/link';
import { FragmentProvider } from '@tinvar/react/deferred-boundary';
import { createTelemetry } from '@tinvar/react/telemetry';

// Pages and deferred subtrees both arrive as RSC streams, decoded by this
// bundle's own client: the same instance that hydrated the page.
const decode = (stream: ReadableStream<Uint8Array>) => createFromReadableStream<ReactNode>(stream);

async function main() {
  // The payload the renderer wrote inline, as self.__rsc rows.
  const initial = await decode(inlinePayloadStream());

  // The root each page is mounted into, and the router that visits them,
  // over the RSC protocol, with the build and route token the renderer
  // wrote into the page.
  const { PageRoot, prepare } = createPageRoot({ decode });
  const router = createRouter({ prepare });

  // Web vitals and script errors, to /_vitals and /_errors.
  const telemetry = createTelemetry({ router }).start();

  // Dev mode: an edited server component is rendered again by the dev
  // server, which says so with `rsc:update`; the page is asked for again
  // through PHP, client state kept.
  import.meta.hot?.on('rsc:update', () => {
    void router.reload({ preserveScroll: true });
  });

  await router.ready;

  // Only the 'use client' subtrees hydrate; server components ship no
  // JavaScript.
  hydrateRoot(
    document,
    <RouterProvider router={router}>
      <FragmentProvider decode={decode}>
        <PageRoot initial={initial} />
      </FragmentProvider>
    </RouterProvider>,
    {
      onCaughtError: telemetry.onCaughtError,
      onUncaughtError: telemetry.onUncaughtError,
      onRecoverableError: telemetry.onRecoverableError,
    },
  );
}

void main();
