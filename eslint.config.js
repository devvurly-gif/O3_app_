import js from '@eslint/js'
import ts from 'typescript-eslint'
import pluginVue from 'eslint-plugin-vue'
import prettierConfig from '@vue/eslint-config-prettier'

export default [
  // ── Global ignores ────────────────────────────────────────────────────────
  { ignores: ['public/**', 'vendor/**', 'node_modules/**', '*.d.ts'] },

  // ── Base JS rules ─────────────────────────────────────────────────────────
  js.configs.recommended,

  // ── TypeScript rules ──────────────────────────────────────────────────────
  ...ts.configs.recommended,

  // ── Vue rules ─────────────────────────────────────────────────────────────
  ...pluginVue.configs['flat/recommended'],

  // ── Vue + TS parser ───────────────────────────────────────────────────────
  {
    files: ['**/*.vue'],
    languageOptions: {
      parserOptions: {
        parser: ts.parser,
      },
    },
  },

  // ── Project-specific overrides ────────────────────────────────────────────
  {
    files: ['resources/js/**/*.{ts,vue}'],
    languageOptions: {
      globals: {
        // Les globals du navigateur sont fournis par la lib DOM de TypeScript,
        // pas par cette liste : `no-undef` est désactivé plus bas.
      },
    },
    rules: {
      // TypeScript résout lui-même les identifiants, et connaît les globals du
      // DOM que cette règle ignore (KeyboardEvent, navigator, confirm…). La
      // laisser active ne produisait que des faux positifs ; c'est `vue-tsc`,
      // bloquant en CI, qui garantit qu'aucun identifiant n'est inconnu.
      'no-undef': 'off',
      // Allow `any` for now — tighten later
      '@typescript-eslint/no-explicit-any': 'warn',
      // Unused vars prefixed with _ are OK
      '@typescript-eslint/no-unused-vars': ['warn', { argsIgnorePattern: '^_', varsIgnorePattern: '^_' }],
      // Vue
      'vue/multi-word-component-names': 'off',
      'vue/no-v-html': 'off',
      // General
      'no-console': ['warn', { allow: ['warn', 'error'] }],
      'vue/no-side-effects-in-computed-properties': 'warn',
    },
  },

  // ── Prettier (must be last) ───────────────────────────────────────────────
  prettierConfig,
]
