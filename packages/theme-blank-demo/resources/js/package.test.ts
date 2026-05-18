import { describe, it, expect } from 'vitest';
import * as fs from 'node:fs';
import * as path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const pkgRoot = path.resolve(__dirname, '../../');

/**
 * Walk up from __dirname until we find the directory that contains vite.config.ts.
 * This works whether the test is run from packages/theme-blank-demo or
 * vendor/markommerce/theme-blank-demo (the latter is a symlinked copy).
 */
function findRepoRoot(startDir: string): string {
  let dir = startDir;
  while (dir !== path.dirname(dir)) {
    if (fs.existsSync(path.join(dir, 'vite.config.ts'))) {
      return dir;
    }
    dir = path.dirname(dir);
  }
  // Fallback
  return path.resolve(startDir, '../../../../');
}

const repoRoot = findRepoRoot(__dirname);

function readFile(filePath: string): string {
  try {
    return fs.readFileSync(filePath, 'utf-8');
  } catch {
    return '';
  }
}

const packageJson = JSON.parse(readFile(path.join(pkgRoot, 'package.json'))) as Record<string, unknown>;
const markommerce = packageJson['markommerce'] as Record<string, unknown> | undefined;

const mainTsPath = path.join(__dirname, 'main.ts');
const indexTsPath = path.join(__dirname, 'index.ts');
const extensionsTsPath = path.join(__dirname, 'extensions.ts');

const mainTs = readFile(mainTsPath);
const indexTs = readFile(indexTsPath);
const extensionsTs = readFile(extensionsTsPath);

describe('theme-blank-demo package wiring', () => {
  it('the package.json declares markommerce.extension pointing at ./resources/js/index.ts', () => {
    expect(markommerce).toBeDefined();
    expect(markommerce?.['extension']).toBe('./resources/js/index.ts');
  });

  it('the package.json declares markommerce.priority as 1010 — higher than @markommerce/theme-blank (100) and one above @markommerce/frontend-demo (1000)', () => {
    expect(markommerce?.['priority']).toBe(1010);
  });

  it('main.ts imports open-props/style.css so Vite emits the Open Props stylesheet link', () => {
    expect(fs.existsSync(mainTsPath)).toBe(true);
    expect(mainTs).toContain("import 'open-props/style.css'");
  });

  it('main.ts imports @markommerce/frontend/css/layers.css before any @markommerce/theme-blank stylesheet (layer-order contract)', () => {
    expect(mainTs).toContain("import '@markommerce/frontend/css/layers.css'");
    const layersIdx = mainTs.indexOf("import '@markommerce/frontend/css/layers.css'");
    const themeBlankCssIdx = mainTs.indexOf("import '@markommerce/theme-blank/css/");
    expect(layersIdx).toBeGreaterThan(-1);
    expect(themeBlankCssIdx).toBeGreaterThan(-1);
    expect(layersIdx).toBeLessThan(themeBlankCssIdx);
  });

  it('main.ts imports @markommerce/theme-blank/css/tokens.css, base.css, and layouts.css in that order', () => {
    const tokensIdx = mainTs.indexOf("import '@markommerce/theme-blank/css/tokens.css'");
    const baseIdx = mainTs.indexOf("import '@markommerce/theme-blank/css/base.css'");
    const layoutsIdx = mainTs.indexOf("import '@markommerce/theme-blank/css/layouts.css'");
    expect(tokensIdx).toBeGreaterThan(-1);
    expect(baseIdx).toBeGreaterThan(-1);
    expect(layoutsIdx).toBeGreaterThan(-1);
    expect(tokensIdx).toBeLessThan(baseIdx);
    expect(baseIdx).toBeLessThan(layoutsIdx);
  });

  it("main.ts imports the @markommerce/theme-blank package main entry — the side-effect module that registers every primitive and form control (assert \"import '@markommerce/theme-blank'\" appears, not the /js/components subpath which is not exported)", () => {
    expect(mainTs).toContain("import '@markommerce/theme-blank'");
    expect(mainTs).not.toContain("import '@markommerce/theme-blank/js/components'");
  });

  it('main.ts calls defineAllComponents() exactly once at the bottom', () => {
    const matches = mainTs.match(/defineAllComponents\(\)/g);
    expect(matches).not.toBeNull();
    expect(matches?.length).toBe(1);
    // Confirm it's near the bottom — after the imports
    const defineIdx = mainTs.indexOf('defineAllComponents()');
    const importLines = mainTs.split('\n').filter((l) => l.startsWith('import '));
    const lastImportLine = importLines[importLines.length - 1] ?? '';
    const lastImportIdx = mainTs.lastIndexOf(lastImportLine);
    expect(defineIdx).toBeGreaterThan(lastImportIdx);
  });

  it('the index.ts entry does NOT call registerBase or addMixin (theme-blank-demo contributes no components of its own)', () => {
    expect(fs.existsSync(indexTsPath)).toBe(true);
    expect(indexTs).not.toContain('registerBase');
    expect(indexTs).not.toContain('addMixin');
  });

  it('the hand-maintained extensions.ts lives at packages/theme-blank-demo/resources/js/extensions.ts (NOT under .generated/) so its hand-maintained status is signalled by its location', () => {
    expect(fs.existsSync(extensionsTsPath)).toBe(true);
    const generatedPath = path.join(__dirname, '.generated/extensions.ts');
    expect(fs.existsSync(generatedPath)).toBe(false);
  });

  it('extensions.ts contains a "hand-maintained" warning comment at the top so a maintainer does not mistake it for a scanner-generated file', () => {
    expect(extensionsTs.toLowerCase()).toContain('hand-maintained');
  });

  it('extensions.ts imports @markommerce/frontend, @markommerce/theme-blank, and @markommerce/theme-blank-demo in priority order', () => {
    const frontendIdx = extensionsTs.indexOf("import '@markommerce/frontend'");
    const themeBlankIdx = extensionsTs.indexOf("import '@markommerce/theme-blank'");
    const demoIdx = extensionsTs.indexOf("import '@markommerce/theme-blank-demo'");
    expect(frontendIdx).toBeGreaterThan(-1);
    expect(themeBlankIdx).toBeGreaterThan(-1);
    expect(demoIdx).toBeGreaterThan(-1);
    expect(frontendIdx).toBeLessThan(themeBlankIdx);
    expect(themeBlankIdx).toBeLessThan(demoIdx);
  });

  it("main.ts imports './extensions' (not './.generated/extensions')", () => {
    expect(mainTs).toContain("import './extensions'");
    expect(mainTs).not.toContain("import './.generated/extensions'");
  });
});

describe('vite.config.ts and tsconfig.json wiring', () => {
  const viteConfigPath = path.join(repoRoot, 'vite.config.ts');
  const tsConfigPath = path.join(repoRoot, 'tsconfig.json');

  const viteConfig = readFile(viteConfigPath);
  const tsConfig = readFile(tsConfigPath);

  it('vite.config.ts rollupOptions.input is an object containing entries for both frontend-demo/main.ts and theme-blank-demo/main.ts', () => {
    // The input should not be a single string pointing only to frontend-demo
    expect(viteConfig).toContain('frontend-demo/resources/js/main.ts');
    expect(viteConfig).toContain('theme-blank-demo/resources/js/main.ts');
    // It should be an object (not a simple string assignment)
    // An object input would have key: value pairs — look for at least two entries
    const inputMatch = viteConfig.match(/input\s*:\s*\{([^}]+)\}/s);
    expect(inputMatch).not.toBeNull();
    expect(inputMatch?.[1]).toContain('frontend-demo');
    expect(inputMatch?.[1]).toContain('theme-blank-demo');
  });

  it('vite.config.ts resolve.alias includes an entry for @markommerce/theme-blank-demo pointing at packages/theme-blank-demo', () => {
    expect(viteConfig).toContain('@markommerce/theme-blank-demo');
    expect(viteConfig).toContain('packages/theme-blank-demo');
  });

  it('tsconfig.json compilerOptions.paths includes @markommerce/theme-blank-demo so TypeScript resolves the alias the same way Vite does', () => {
    const tsConfigObj = JSON.parse(tsConfig) as Record<string, unknown>;
    const compilerOptions = tsConfigObj['compilerOptions'] as Record<string, unknown>;
    const paths = compilerOptions['paths'] as Record<string, unknown>;
    expect(paths).toHaveProperty('@markommerce/theme-blank-demo');
  });
});
