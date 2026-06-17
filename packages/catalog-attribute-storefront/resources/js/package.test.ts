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

const facetSidebarCssPath = path.join(pkgRoot, 'resources/css/components/facet-sidebar.css');
const facetSidebarCss = readFile(facetSidebarCssPath);

const viteConfigPath = path.resolve(pkgRoot, '../../vite.config.ts');
const viteConfig = readFile(viteConfigPath);

const baseLattePath = path.resolve(pkgRoot, '../theme-blank/resources/views/layout/base.latte');
const baseLatte = readFile(baseLattePath);

describe('catalog-attribute-storefront package frontend wiring', () => {
  it('declares the catalog-attribute-storefront npm package as a markommerce frontend extension', () => {
    expect(fs.existsSync(packageJsonPath)).toBe(true);
    expect(markommerce).toBeDefined();
    expect(markommerce?.['extension']).toBe('./resources/js/index.ts');
    expect(typeof markommerce?.['priority']).toBe('number');
    expect(packageJson['name']).toBe('@markommerce/catalog-attribute-storefront');
  });

  it('imports the facet-sidebar stylesheet from the package js entry', () => {
    expect(fs.existsSync(indexTsPath)).toBe(true);
    expect(indexTs).toContain("import '../css/components/facet-sidebar.css'");
  });

  it('ships a facet-sidebar css file under resources/css/components', () => {
    expect(fs.existsSync(facetSidebarCssPath)).toBe(true);
  });

  it('wraps the facet-sidebar stylesheet in the components cascade layer', () => {
    expect(facetSidebarCss).toContain('@layer components');
  });

  it('registers the package js entry as a rollup build input in vite.config.ts', () => {
    expect(fs.existsSync(viteConfigPath)).toBe(true);
    expect(viteConfig).toContain('catalogAttributeStorefront');
    expect(viteConfig).toContain('packages/catalog-attribute-storefront/resources/js/index.ts');
  });

  it('references the package js entry via vite() in the theme-blank base layout template', () => {
    expect(fs.existsSync(baseLattePath)).toBe(true);
    expect(baseLatte).toContain("{vite('packages/catalog-attribute-storefront/resources/js/index.ts')}");
  });
});
