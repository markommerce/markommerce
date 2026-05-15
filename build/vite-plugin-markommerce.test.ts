import { describe, it, expect, beforeEach, afterEach } from 'vitest';
import * as fs from 'node:fs';
import * as path from 'node:path';
import * as os from 'node:os';
import markommerceModuleScanner from './vite-plugin-markommerce.js';

// Helper: create a temp directory with a fixture package structure
function createTempPackagesDir(): string {
  return fs.mkdtempSync(path.join(os.tmpdir(), 'markommerce-test-'));
}

function createPackage(
  packagesDir: string,
  name: string,
  packageJson: Record<string, unknown>,
): void {
  const pkgDir = path.join(packagesDir, name);
  fs.mkdirSync(pkgDir, { recursive: true });
  fs.writeFileSync(path.join(pkgDir, 'package.json'), JSON.stringify(packageJson));
}

function cleanupDir(dir: string): void {
  fs.rmSync(dir, { recursive: true, force: true });
}

// Minimal plugin invocation helper for buildStart
async function invokeBuildStart(
  packagesPath: string,
  outputPath: string,
): Promise<void> {
  const plugin = markommerceModuleScanner({ packagesPath, outputPath });
  if (typeof plugin.buildStart === 'function') {
    await (plugin.buildStart as () => Promise<void>)();
  }
}

// Helper for handleHotUpdate
async function invokeHandleHotUpdate(
  packagesPath: string,
  outputPath: string,
  file: string,
): Promise<void> {
  const plugin = markommerceModuleScanner({ packagesPath, outputPath });
  if (typeof plugin.handleHotUpdate === 'function') {
    await (plugin.handleHotUpdate as (ctx: { file: string }) => Promise<void>)({ file });
  }
}

describe('vite-plugin-markommerce', () => {
  let packagesDir: string;
  let outputDir: string;
  let outputPath: string;

  beforeEach(() => {
    packagesDir = createTempPackagesDir();
    outputDir = fs.mkdtempSync(path.join(os.tmpdir(), 'markommerce-out-'));
    outputPath = path.join(outputDir, 'extensions.ts');
  });

  afterEach(() => {
    cleanupDir(packagesDir);
    cleanupDir(outputDir);
  });

  it('scans packagesPath and discovers packages with a markommerce block in package.json', async () => {
    createPackage(packagesDir, 'frontend', {
      name: '@markommerce/frontend',
      markommerce: { extension: './resources/js/index.ts', priority: 0 },
    });
    createPackage(packagesDir, 'catalog', {
      name: '@markommerce/catalog',
      markommerce: { extension: './resources/js/index.ts', priority: 10 },
    });

    await invokeBuildStart(packagesDir, outputPath);

    const content = fs.readFileSync(outputPath, 'utf-8');
    expect(content).toContain('@markommerce/frontend');
    expect(content).toContain('@markommerce/catalog');
  });

  it('skips packages without a markommerce block silently', async () => {
    createPackage(packagesDir, 'no-block', {
      name: '@markommerce/no-block',
    });
    createPackage(packagesDir, 'catalog', {
      name: '@markommerce/catalog',
      markommerce: { extension: './resources/js/index.ts', priority: 10 },
    });

    await invokeBuildStart(packagesDir, outputPath);

    const content = fs.readFileSync(outputPath, 'utf-8');
    expect(content).not.toContain('@markommerce/no-block');
    expect(content).toContain('@markommerce/catalog');
  });

  it('skips package directories that have no package.json at all (e.g. packages/core/ today)', async () => {
    // Create a directory without a package.json
    fs.mkdirSync(path.join(packagesDir, 'core'), { recursive: true });
    createPackage(packagesDir, 'catalog', {
      name: '@markommerce/catalog',
      markommerce: { extension: './resources/js/index.ts', priority: 10 },
    });

    await invokeBuildStart(packagesDir, outputPath);

    const content = fs.readFileSync(outputPath, 'utf-8');
    expect(content).toContain('@markommerce/catalog');
    // Should not throw and should still generate the file
  });

  it('skips packages with a malformed package.json silently', async () => {
    const malformedDir = path.join(packagesDir, 'malformed');
    fs.mkdirSync(malformedDir, { recursive: true });
    fs.writeFileSync(path.join(malformedDir, 'package.json'), '{ invalid json ');

    createPackage(packagesDir, 'catalog', {
      name: '@markommerce/catalog',
      markommerce: { extension: './resources/js/index.ts', priority: 10 },
    });

    await invokeBuildStart(packagesDir, outputPath);

    const content = fs.readFileSync(outputPath, 'utf-8');
    expect(content).toContain('@markommerce/catalog');
    // Should not throw and malformed package should be skipped
  });

  it('writes the generated extensions file to outputPath on buildStart', async () => {
    createPackage(packagesDir, 'frontend', {
      name: '@markommerce/frontend',
      markommerce: { extension: './resources/js/index.ts', priority: 0 },
    });

    await invokeBuildStart(packagesDir, outputPath);

    expect(fs.existsSync(outputPath)).toBe(true);
    const content = fs.readFileSync(outputPath, 'utf-8');
    expect(content.length).toBeGreaterThan(0);
  });

  it('orders @markommerce/frontend first regardless of priority value', async () => {
    createPackage(packagesDir, 'catalog', {
      name: '@markommerce/catalog',
      markommerce: { extension: './resources/js/index.ts', priority: 1 },
    });
    createPackage(packagesDir, 'frontend', {
      name: '@markommerce/frontend',
      markommerce: { extension: './resources/js/index.ts', priority: 999 },
    });
    createPackage(packagesDir, 'cart', {
      name: '@markommerce/cart',
      markommerce: { extension: './resources/js/index.ts', priority: 2 },
    });

    await invokeBuildStart(packagesDir, outputPath);

    const content = fs.readFileSync(outputPath, 'utf-8');
    const frontendPos = content.indexOf('@markommerce/frontend');
    const catalogPos = content.indexOf('@markommerce/catalog');
    const cartPos = content.indexOf('@markommerce/cart');

    expect(frontendPos).toBeLessThan(catalogPos);
    expect(frontendPos).toBeLessThan(cartPos);
  });

  it('orders remaining modules by ascending priority, breaking ties alphabetically by name', async () => {
    createPackage(packagesDir, 'catalog', {
      name: '@markommerce/catalog',
      markommerce: { extension: './resources/js/index.ts', priority: 10 },
    });
    createPackage(packagesDir, 'cart', {
      name: '@markommerce/cart',
      markommerce: { extension: './resources/js/index.ts', priority: 10 },
    });
    createPackage(packagesDir, 'checkout', {
      name: '@markommerce/checkout',
      markommerce: { extension: './resources/js/index.ts', priority: 5 },
    });

    await invokeBuildStart(packagesDir, outputPath);

    const content = fs.readFileSync(outputPath, 'utf-8');
    const checkoutPos = content.indexOf('@markommerce/checkout');
    const cartPos = content.indexOf('@markommerce/cart');
    const catalogPos = content.indexOf('@markommerce/catalog');

    // checkout (priority 5) before cart and catalog (priority 10)
    expect(checkoutPos).toBeLessThan(cartPos);
    expect(checkoutPos).toBeLessThan(catalogPos);
    // cart before catalog (alphabetical tiebreak)
    expect(cartPos).toBeLessThan(catalogPos);
  });

  it('emits one side-effect import line per discovered module', async () => {
    createPackage(packagesDir, 'frontend', {
      name: '@markommerce/frontend',
      markommerce: { extension: './resources/js/index.ts', priority: 0 },
    });
    createPackage(packagesDir, 'catalog', {
      name: '@markommerce/catalog',
      markommerce: { extension: './resources/js/index.ts', priority: 10 },
    });

    await invokeBuildStart(packagesDir, outputPath);

    const content = fs.readFileSync(outputPath, 'utf-8');
    // Side-effect imports look like: import 'some/path';
    const importLines = content.split('\n').filter(line => /^import '[^']+';$/.test(line.trim()));
    expect(importLines).toHaveLength(2);
  });

  it('emits a LOADED_MODULES const containing name and priority for each discovered module', async () => {
    createPackage(packagesDir, 'frontend', {
      name: '@markommerce/frontend',
      markommerce: { extension: './resources/js/index.ts', priority: 0 },
    });
    createPackage(packagesDir, 'catalog', {
      name: '@markommerce/catalog',
      markommerce: { extension: './resources/js/index.ts', priority: 10 },
    });

    await invokeBuildStart(packagesDir, outputPath);

    const content = fs.readFileSync(outputPath, 'utf-8');
    expect(content).toContain('LOADED_MODULES');
    expect(content).toMatch(/@markommerce\/frontend.*priority.*0/s);
    expect(content).toMatch(/@markommerce\/catalog.*priority.*10/s);
  });

  it('regenerates the file on handleHotUpdate when a packages/*/package.json changes', async () => {
    createPackage(packagesDir, 'frontend', {
      name: '@markommerce/frontend',
      markommerce: { extension: './resources/js/index.ts', priority: 0 },
    });

    await invokeBuildStart(packagesDir, outputPath);

    // Add a new package after initial build
    createPackage(packagesDir, 'catalog', {
      name: '@markommerce/catalog',
      markommerce: { extension: './resources/js/index.ts', priority: 10 },
    });

    // Trigger HMR for a package.json change
    const changedFile = path.join(packagesDir, 'catalog', 'package.json');
    await invokeHandleHotUpdate(packagesDir, outputPath, changedFile);

    const content = fs.readFileSync(outputPath, 'utf-8');
    expect(content).toContain('@markommerce/catalog');
  });

  it('does NOT regenerate the file for unrelated package.json changes outside packagesPath', async () => {
    createPackage(packagesDir, 'frontend', {
      name: '@markommerce/frontend',
      markommerce: { extension: './resources/js/index.ts', priority: 0 },
    });

    await invokeBuildStart(packagesDir, outputPath);
    const originalContent = fs.readFileSync(outputPath, 'utf-8');
    const originalMtime = fs.statSync(outputPath).mtimeMs;

    // Simulate a small delay to ensure mtime would differ
    await new Promise(resolve => setTimeout(resolve, 10));

    // Trigger HMR for an unrelated package.json (outside packagesPath)
    const unrelatedFile = '/some/other/project/package.json';
    await invokeHandleHotUpdate(packagesDir, outputPath, unrelatedFile);

    const newMtime = fs.statSync(outputPath).mtimeMs;
    expect(newMtime).toBe(originalMtime);
  });

  it('resolves the extension path relative to each package directory', async () => {
    createPackage(packagesDir, 'frontend', {
      name: '@markommerce/frontend',
      markommerce: { extension: './resources/js/index.ts', priority: 0 },
    });

    await invokeBuildStart(packagesDir, outputPath);

    const content = fs.readFileSync(outputPath, 'utf-8');
    // The import should be an absolute path or a properly resolved path from the package dir
    const frontendPkgDir = path.join(packagesDir, 'frontend');
    const expectedPath = path.resolve(frontendPkgDir, './resources/js/index.ts');
    expect(content).toContain(expectedPath);
  });

  it('writes the file with a "DO NOT EDIT" header comment', async () => {
    createPackage(packagesDir, 'frontend', {
      name: '@markommerce/frontend',
      markommerce: { extension: './resources/js/index.ts', priority: 0 },
    });

    await invokeBuildStart(packagesDir, outputPath);

    const content = fs.readFileSync(outputPath, 'utf-8');
    expect(content).toMatch(/DO NOT EDIT/i);
  });
});
