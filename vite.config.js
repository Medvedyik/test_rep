// vite.config.js
import { defineConfig } from 'vite';
import usePHP from 'vite-plugin-php';

export default defineConfig({
  plugins: [
    usePHP({
      binary: 'php',
      entry: 'index.php',
    }),
  ],
  server: {
    port: 3000, // можете указать любой удобный порт
  },
});