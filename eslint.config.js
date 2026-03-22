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
        // Browser globals
        window: 'readonly',
        document: 'readonly',
        setTimeout: 'readonly',
        clearTimeout: 'readonly',
        setInterval: 'readonly',
        clearInterval: 'readonly',
        console: 'readonly',
        alert: 'readonly',
        FormData: 'readonly',
        Blob: 'readonly',
        URL: 'readonly',
        URLSearchParams: 'readonly',
        fetch: 'readonly',
        AbortController: 'readonly',
        Event: 'readonly',
        HTMLElement: 'readonly',
        HTMLInputElement: 'readonly',
        localStorage: 'readonly',
        sessionStorage: 'readonly',
      },
    },
    rules: {
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
