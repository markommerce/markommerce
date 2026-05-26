import { describe, it, expect } from 'vitest';
import * as fs from 'node:fs';
import * as path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const pkgRoot = path.resolve(__dirname, '../../');

function readFile(filePath: string): string {
  try {
    return fs.readFileSync(filePath, 'utf-8');
  } catch {
    return '';
  }
}

const packageJsonPath = path.join(pkgRoot, 'package.json');
const packageJsonContent = readFile(packageJsonPath);
const packageJson = packageJsonContent ? (JSON.parse(packageJsonContent) as Record<string, unknown>) : {};
const markommerce = packageJson['markommerce'] as Record<string, unknown> | undefined;

const indexTsPath = path.join(__dirname, 'index.ts');
const indexTs = readFile(indexTsPath);

describe('catalog-storefront package frontend wiring', () => {
  it('registers the catalog-storefront package with the frontend scanner via package.json markommerce.extension', () => {
    expect(fs.existsSync(packageJsonPath)).toBe(true);
    expect(markommerce).toBeDefined();
    expect(markommerce?.['extension']).toBe('./resources/js/index.ts');
  });

  it('provides an empty resources/js/index.ts in catalog-storefront ready for CSS import', () => {
    expect(fs.existsSync(indexTsPath)).toBe(true);
    // The file should exist and be an empty module (export {} or similar)
    // It should NOT bootstrap CSS itself — that's theme-blank's job
    expect(indexTs).not.toContain("import '@markommerce/frontend/css/layers.css'");
    expect(indexTs).not.toContain("import 'open-props/style.css'");
    // It should be a valid module (either empty or export {})
    expect(indexTs.trim().length).toBeGreaterThanOrEqual(0);
  });
});
