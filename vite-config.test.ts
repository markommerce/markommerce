import { describe, it, expect } from 'vitest';
import * as fs from 'node:fs';
import * as path from 'node:path';
import { fileURLToPath } from 'node:url';

// Resolve repo root from this test file location
const __filename = fileURLToPath(import.meta.url);
const repoRoot = path.dirname(__filename);

const viteConfigPath = path.join(repoRoot, 'vite.config.ts');

function readViteConfig(): string {
  return fs.readFileSync(viteConfigPath, 'utf-8');
}

describe('vite.config.ts', () => {
  it('loads the markommerceModuleScanner plugin with the agreed packagesPath and outputPath', () => {
    const content = readViteConfig();
    // Must import markommerceModuleScanner from ./build/vite-plugin-markommerce
    expect(content).toMatch(/import\s+markommerceModuleScanner\s+from\s+['"]\.\/build\/vite-plugin-markommerce['"]/);
    // Must use packagesPath pointing to packages/
    expect(content).toContain('packagesPath');
    expect(content).toMatch(/packages['"]/);
    // Must use outputPath pointing to the generated extensions file
    expect(content).toContain('outputPath');
    expect(content).toContain('.generated');
    expect(content).toContain('extensions.ts');
  });

  it('sets the build target to es2022', () => {
    const content = readViteConfig();
    expect(content).toMatch(/target\s*:\s*['"]es2022['"]/i);
  });

  it('resolves outDir from MARKOMMERCE_CONSUMER_PUBLIC env var falling back to public/build inside the repo', () => {
    const content = readViteConfig();
    expect(content).toContain('MARKOMMERCE_CONSUMER_PUBLIC');
    expect(content).toMatch(/public\/build/);
    // Must use env var with fallback pattern
    expect(content).toMatch(/process\.env\[['"]MARKOMMERCE_CONSUMER_PUBLIC['"]\]/);
  });

  it('sets manifest to true so marko/vite can read the produced manifest under .vite/manifest.json', () => {
    const content = readViteConfig();
    expect(content).toMatch(/manifest\s*:\s*true/);
  });

  it('declares the @markommerce/frontend aliases for js and css subpaths', () => {
    const content = readViteConfig();
    // Must have alias for @markommerce/frontend
    expect(content).toContain('@markommerce/frontend');
    // Must reference index.ts
    expect(content).toContain('index.ts');
    // Must have alias for css subpath
    expect(content).toContain('resources/css');
    // Must have alias for js subpath
    expect(content).toContain('resources/js');
  });

  it('points the dev server at port 5173 with strictPort enabled', () => {
    const content = readViteConfig();
    expect(content).toMatch(/port\s*:\s*5173/);
    expect(content).toMatch(/strictPort\s*:\s*true/);
  });

  it('sources css.postcss from the repo-root postcss.config.js', () => {
    const content = readViteConfig();
    expect(content).toMatch(/postcss/);
    expect(content).toContain('postcss.config.js');
  });

  it('sets the build input to packages/frontend-demo/resources/js/main.ts', () => {
    const content = readViteConfig();
    expect(content).toContain('frontend-demo');
    expect(content).toContain('main.ts');
    expect(content).toMatch(/input/);
  });

  it('produces hashed asset filenames under assets/', () => {
    const content = readViteConfig();
    expect(content).toContain('assets/');
    expect(content).toMatch(/\[hash\]/);
    expect(content).toMatch(/assetFileNames/);
  });

  it('vite build exits 0 once the demo package and its main entry exist', async () => {
    // This test verifies the build actually works by running npm run build in the repo root.
    // The test runs inside the node Docker container, so we run it directly without docker compose.
    const { execSync } = await import('node:child_process');
    expect(() => {
      execSync('npm run build', {
        stdio: 'pipe',
        timeout: 120000,
        cwd: repoRoot,
      });
    }).not.toThrow();
  });

  it('the root package.json declares vite as a devDependency', () => {
    const pkgJsonPath = path.join(repoRoot, 'package.json');
    const pkgJson = JSON.parse(fs.readFileSync(pkgJsonPath, 'utf-8')) as Record<string, unknown>;
    const devDeps = pkgJson['devDependencies'] as Record<string, string> | undefined;
    expect(devDeps).toBeDefined();
    expect(devDeps?.['vite']).toBeDefined();
  });
});
