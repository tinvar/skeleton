import stylex from '@stylexjs/unplugin';
import { tinvarTesting } from '@tinvar/renderer/vitest';
import { defineConfig } from 'vitest/config';

// The component tests: Vitest and React Testing Library in jsdom, against
// the mocks and fakes `php tinvar codegen` writes under resources/types/testing.
// A config of its own, not vite.config.ts: that one is the build, and
// tinvarTesting() refuses to be in a build.
//
// StyleX compiles the components' stylex.create() calls -- uncompiled, they
// throw -- without its dev server hook, which would keep the run from
// exiting. Meridian is inlined so it is compiled too.
const compileStylex = { ...stylex.vite(), configureServer: undefined };

export default defineConfig({
  plugins: [compileStylex, tinvarTesting()],
  test: {
    include: ['tests/components/**/*.dom.test.tsx'],
    environment: 'jsdom',
    server: { deps: { inline: ['@meridian/ui'] } },
  },
});
