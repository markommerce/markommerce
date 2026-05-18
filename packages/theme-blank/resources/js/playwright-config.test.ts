import { describe, it, expect } from 'vitest';
import { readFileSync } from 'node:fs';
import { resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);

const repoRoot = resolve(__dirname, '../../../../');
const rootPackageJson = JSON.parse(readFileSync(resolve(repoRoot, 'package.json'), 'utf-8'));
const rootComposerJson = JSON.parse(readFileSync(resolve(repoRoot, 'composer.json'), 'utf-8'));

describe('Playwright + CLS smoke test infrastructure', () => {
  it('it installs @playwright/test as a repo-root devDependency', () => {
    expect(rootPackageJson.devDependencies).toBeDefined();
    expect(rootPackageJson.devDependencies['@playwright/test']).toBeDefined();
  });

  it('it adds a "test:cls" script to the root package.json invoking playwright test', () => {
    expect(rootPackageJson.scripts).toBeDefined();
    expect(rootPackageJson.scripts['test:cls']).toBeDefined();
    expect(rootPackageJson.scripts['test:cls']).toContain('playwright test');
  });

  it('the root composer.json\'s test:all script remains PHP-only', () => {
    const testAll: string = rootComposerJson.scripts['test:all'];
    expect(testAll).not.toContain('playwright');
    expect(testAll).not.toContain('npm');
    expect(testAll).toContain('pest');
  });

  it('it ships a playwright.config.ts at the repo root with testDir pointed at packages/theme-blank/tests/Browser', () => {
    const playwrightConfig = readFileSync(resolve(repoRoot, 'playwright.config.ts'), 'utf-8');
    expect(playwrightConfig).toContain('packages/theme-blank/tests/Browser');
    expect(playwrightConfig).toContain('testDir');
  });

  it('it ships a fixture HTML at packages/theme-blank/tests/Browser/fixtures/base-page.html', () => {
    const fixtureHtml = readFileSync(
      resolve(repoRoot, 'packages/theme-blank/tests/Browser/fixtures/base-page.html'),
      'utf-8',
    );
    expect(fixtureHtml).toBeTruthy();
    expect(fixtureHtml.length).toBeGreaterThan(0);
  });

  it('the fixture includes the tokens, base, and layouts CSS inline', () => {
    const fixtureHtml = readFileSync(
      resolve(repoRoot, 'packages/theme-blank/tests/Browser/fixtures/base-page.html'),
      'utf-8',
    );
    // Should contain content from tokens.css
    expect(fixtureHtml).toContain('--mk-color-primary');
    // Should contain content from base.css
    expect(fixtureHtml).toContain('box-sizing: border-box');
    // Should contain content from layouts.css
    expect(fixtureHtml).toContain('mk-layout-1col');
  });

  it('the fixture renders a 1column.latte-shaped DOM (doctype, html lang, head, body with header/main.mk-layout-1col/footer)', () => {
    const fixtureHtml = readFileSync(
      resolve(repoRoot, 'packages/theme-blank/tests/Browser/fixtures/base-page.html'),
      'utf-8',
    );
    expect(fixtureHtml).toContain('<!doctype html>');
    expect(fixtureHtml).toMatch(/<html[^>]+lang=/i);
    expect(fixtureHtml).toContain('<head>');
    expect(fixtureHtml).toContain('<body>');
    expect(fixtureHtml).toContain('<header');
    expect(fixtureHtml).toContain('mk-layout-1col');
    expect(fixtureHtml).toContain('<main');
    expect(fixtureHtml).toContain('<footer');
  });

  it('it ships a cls.spec.ts Playwright test that observes layout-shift PerformanceObserver entries and asserts the sum is 0', () => {
    const clsSpec = readFileSync(
      resolve(repoRoot, 'packages/theme-blank/tests/Browser/cls.spec.ts'),
      'utf-8',
    );
    expect(clsSpec).toContain('layout-shift');
    expect(clsSpec).toContain('PerformanceObserver');
    expect(clsSpec).toContain('cls');
    expect(clsSpec).toContain('toBe(0)');
  });

  it('the Playwright config restricts to Chromium for Phase 1', () => {
    const playwrightConfig = readFileSync(resolve(repoRoot, 'playwright.config.ts'), 'utf-8');
    expect(playwrightConfig).toContain('chromium');
    expect(playwrightConfig).toContain('projects');
  });

  it('there is a packages/theme-blank/tests/Browser/README.md documenting how Phase 2+ contributors add a new fixture', () => {
    const readme = readFileSync(
      resolve(repoRoot, 'packages/theme-blank/tests/Browser/README.md'),
      'utf-8',
    );
    expect(readme).toBeTruthy();
    expect(readme).toContain('fixture');
    expect(readme.toLowerCase()).toContain('phase 2');
  });
});
