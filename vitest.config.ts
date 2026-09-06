import { fileURLToPath } from 'node:url'
import { defineConfig } from 'vitest/config'
import vue from '@vitejs/plugin-vue'

/**
 * Configuration distincte de vite.config.js : le plugin Laravel attend un
 * contexte d'application servie, dont les tests n'ont pas besoin.
 */
export default defineConfig({
  plugins: [vue()],
  resolve: {
    alias: { '@': fileURLToPath(new URL('./resources/js', import.meta.url)) },
  },
  test: {
    environment: 'node',
    include: ['resources/js/**/*.spec.ts'],
  },
})
