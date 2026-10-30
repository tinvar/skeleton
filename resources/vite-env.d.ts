/// <reference types="vite/client" />
/// <reference types="@vitejs/plugin-rsc/types" />

// The RSC plugin's map of the build's client references, by id, which the
// ssr entry hands @tinvar/renderer/warm: a module only the app's own build
// can import.
declare module 'virtual:vite-rsc/client-references' {
  const references: Record<string, () => Promise<unknown>>;
  export default references;
}
