import stylex from '@stylexjs/unplugin';
import react from '@vitejs/plugin-react';
import rsc from '@vitejs/plugin-rsc';
import {
  buildProfile,
  clientComponentChunks,
  deferredBoundaries,
  queryFunctions,
  serializableProps,
} from '@tinvar/renderer/vite';
import { workbench } from '@tinvar/renderer/workbench';
import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vite';

// Meridian ships uncompiled StyleX, and its files import each other without
// extensions -- so it is bundled into the server builds, where the StyleX
// compiler sees it, rather than left for Node to load at runtime.
const bundled = ['@meridian/ui'];

// Kept, for the CSS it has compiled: in dev StyleX hands it to the browser by
// script, and the workbench's frame runs none.
const styles = stylex.vite();
const compiledCss = () =>
  (styles as unknown as { __stylexCollectCss?: () => string }).__stylexCollectCss?.() ?? '';

// Three environments, as the RSC model requires:
//   rsc     loads modules under the `react-server` condition and serializes the tree
//   ssr     deserializes that stream and renders HTML
//   client  hydrates the 'use client' islands, and nothing else
//
// The same config serves dev mode (`bin/dev`), where Vite's dev server is
// the renderer and these environments render from source.
export default defineConfig(({ command }) => ({
  // StyleX first, so it compiles stylex.create() before the RSC plugin
  // splits the graph. deferredBoundaries() writes dist/rsc/deferred.json --
  // the <Deferred> components and the queries each view reaches -- which
  // `bin/tinvar build:fragments` turns into the manifest PHP holds every
  // render to. queryFunctions() makes the `@queries` modules codegen types.
  // serializableProps() refuses a build where a server component hands a
  // client component a function. buildProfile() records the profile the
  // build was made for, which PHP checks before it serves it. workbench(),
  // in dev alone, draws the pages and components in resources/workbench.tsx
  // on their own at /_vite/__workbench and /_vite/__playground, where
  // TINVAR_ENV says local, development or testing.
  plugins: [
    styles,
    ...(command === 'serve'
      ? [react(), workbench({ module: './resources/workbench.tsx', css: compiledCss })]
      : []),
    rsc({ clientChunks: clientComponentChunks() }),
    deferredBoundaries(),
    queryFunctions(),
    serializableProps(),
    buildProfile(),
  ],
  // `import { add } from '@actions/NoteActions'`: the action references
  // codegen writes, one module per class (tsconfig maps the same).
  resolve: { alias: { '@actions': fileURLToPath(new URL('./resources/types/actions', import.meta.url)) } },
  environments: {
    rsc: {
      resolve: { noExternal: bundled },
      build: { rollupOptions: { input: { index: './resources/entries/rsc.tsx' } } },
    },
    ssr: {
      resolve: { noExternal: [...bundled, 'lucide-react'] },
      build: { rollupOptions: { input: { index: './resources/entries/ssr.tsx' } } },
    },
    client: {
      optimizeDeps: { include: ['@stylexjs/stylex'] },
      build: { rollupOptions: { input: { index: './resources/entries/browser.tsx' } } },
    },
  },
  build: {
    emptyOutDir: false,
    rollupOptions: {
      // The RSC plugin reads 'use client' before bundling; the bundler then
      // reports each such line it drops as if it still mattered.
      onwarn(warning, warn) {
        if (warning.code === 'MODULE_LEVEL_DIRECTIVE' && warning.message.includes('"use client"')) return;
        warn(warning);
      },
    },
  },
  // public/ holds the PHP front controller and the worker, not static
  // assets: as Vite's publicDir it would be copied into dist/client, the
  // directory the server serves files from.
  publicDir: false,
}));
