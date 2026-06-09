import { defineConfig } from 'vite';
import * as path from 'node:path';
import { fileURLToPath } from 'node:url';
import markommerceModuleScanner from './build/vite-plugin-markommerce';

const __filename = fileURLToPath(import.meta.url);
const repoRoot = path.dirname(__filename);

const outDir =
  process.env['MARKOMMERCE_CONSUMER_PUBLIC'] ?? path.join(repoRoot, 'public/build');

const packagesPath = path.join(repoRoot, 'packages');
const outputPath = path.join(
  repoRoot,
  'packages',
  'frontend-demo',
  'resources',
  'js',
  '.generated',
  'extensions.ts',
);

export default defineConfig(({ command }) => ({
  plugins: [markommerceModuleScanner({ packagesPath, outputPath })],

  test: {
    exclude: [
      '**/node_modules/**',
      '**/dist/**',
      '**/tests/Browser/**',
    ],
  },

  resolve: {
    alias: [
      {
        find: '@markommerce/theme-blank/css',
        replacement: path.join(repoRoot, 'packages/theme-blank/resources/css'),
      },
      {
        find: '@markommerce/theme-blank',
        replacement: path.join(repoRoot, 'packages/theme-blank/resources/js/index.ts'),
      },
      {
        find: '@markommerce/frontend/css',
        replacement: path.join(repoRoot, 'packages/frontend/resources/css'),
      },
      {
        find: '@markommerce/frontend',
        replacement: path.join(repoRoot, 'packages/frontend/resources/js/index.ts'),
      },
      {
        find: '@markommerce/theme-blank-demo',
        replacement: path.join(repoRoot, 'packages/theme-blank-demo/resources/js/index.ts'),
      },
      {
        find: 'open-props/style.css',
        replacement: path.join(repoRoot, 'node_modules/open-props/open-props.min.css'),
      },
    ],
  },

  css: {
    postcss: path.join(repoRoot, 'postcss.config.js'),
    devSourcemap: command === 'serve',
  },

  build: {
    target: 'es2022',
    outDir,
    emptyOutDir: true,
    manifest: true,
    sourcemap: command === 'serve',
    rollupOptions: {
      input: {
        frontendDemo: path.join(repoRoot, 'packages/frontend-demo/resources/js/main.ts'),
        themeBlankDemo: path.join(repoRoot, 'packages/theme-blank-demo/resources/js/main.ts'),
        // theme-blank's base.latte references this entry via {vite(...)}, so it must
        // be a build input or the manifest lacks it (ViteManifestException at render).
        themeBlank: path.join(repoRoot, 'packages/theme-blank/resources/js/index.ts'),
        catalogStorefront: path.join(repoRoot, 'packages/catalog-storefront/resources/js/index.ts'),
      },
      output: {
        assetFileNames: 'assets/[name].[hash].[ext]',
      },
    },
  },

  server: {
    host: true,
    port: 5173,
    strictPort: true,
    cors: true,
    origin: 'http://localhost:5173',
  },
}));
