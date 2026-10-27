import tsParser from '@typescript-eslint/parser';
import meridian from '@meridian/eslint-plugin';
import tinvar from '@tinvar/renderer/eslint';

export default [
  {
    files: ['**/*.{ts,tsx}'],
    languageOptions: { parser: tsParser, parserOptions: { ecmaFeatures: { jsx: true } } },
  },
  // Meridian's rules for the views.
  meridian.configs.recommended,
  // No state in a server module: one renderer process serves every user's
  // render. A 'use client' module is left out by the rule itself. And a
  // query called inside a can() branch checks the same ability itself,
  // read against resources/types/queries.json.
  { ...tinvar.configs.server, files: [...tinvar.configs.server.files, 'resources/lib/**/*.{ts,tsx}'] },
];
