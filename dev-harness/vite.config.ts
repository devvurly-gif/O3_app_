import { fileURLToPath } from 'node:url'
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'

const resources = fileURLToPath(new URL('../resources/js', import.meta.url))
const httpMock = fileURLToPath(new URL('./mocks/http.ts', import.meta.url))

/**
 * Configuration propre au banc : ni plugin Laravel, ni entree de production.
 *
 * L'alias sur `@/services/http` precede celui sur `@` — Vite retient la
 * premiere entree qui matche, et c'est ce qui coupe les ecrans du reseau.
 */
export default defineConfig({
  root: fileURLToPath(new URL('.', import.meta.url)),
  plugins: [vue(), tailwindcss()],
  resolve: {
    alias: [
      { find: /^@\/services\/http$/, replacement: httpMock },
      { find: /^@\//, replacement: `${resources}/` },
    ],
  },
  server: { port: 5199, strictPort: true },
})
