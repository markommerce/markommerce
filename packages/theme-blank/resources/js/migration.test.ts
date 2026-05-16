import { describe, it, expect } from 'vitest';
import { existsSync, readFileSync } from 'fs';
import { resolve } from 'path';

const repoRoot = resolve(__dirname, '../../../..');

describe('token migration from frontend to theme-blank', () => {
  it('the frontend package no longer ships tokens.css', () => {
    const tokensCssPath = resolve(
      repoRoot,
      'packages/frontend/resources/css/tokens.css',
    );
    expect(existsSync(tokensCssPath)).toBe(false);
  });

  it('the frontend package.json exports no longer declares ./css/tokens.css', () => {
    const pkg = JSON.parse(
      readFileSync(
        resolve(repoRoot, 'packages/frontend/package.json'),
        'utf-8',
      ),
    );
    expect(pkg.exports['./css/tokens.css']).toBeUndefined();
  });

  it('the frontend layers.test.ts no longer asserts presence of unprefixed tokens', () => {
    const layersTestPath = resolve(
      repoRoot,
      'packages/frontend/resources/js/layers.test.ts',
    );
    const content = readFileSync(layersTestPath, 'utf-8');
    expect(content).not.toContain("describe('tokens.css'");
    expect(content).not.toContain('describe("tokens.css"');
  });

  it('the frontend-demo main.ts imports @markommerce/theme-blank/css/tokens.css (not @markommerce/frontend/css/tokens.css)', () => {
    const mainTsPath = resolve(
      repoRoot,
      'packages/frontend-demo/resources/js/main.ts',
    );
    const content = readFileSync(mainTsPath, 'utf-8');
    expect(content).toContain("import '@markommerce/theme-blank/css/tokens.css'");
    expect(content).not.toContain("import '@markommerce/frontend/css/tokens.css'");
  });

  it('the frontend-demo counter.css uses --mk-* prefixed token references exclusively for markommerce tokens', () => {
    const counterCssPath = resolve(
      repoRoot,
      'packages/frontend-demo/resources/css/components/counter.css',
    );
    const content = readFileSync(counterCssPath, 'utf-8');
    // No bare --color-*, --space-*, --radius-* var() calls should remain
    expect(content).not.toMatch(/var\(--color-/);
    expect(content).not.toMatch(/var\(--space-/);
    expect(content).not.toMatch(/var\(--radius-[^2-9]/);
    // Should have --mk-* prefixed references
    expect(content).toContain('--mk-');
  });

  it('the frontend-demo counter.css no longer references the dangling --color-text token', () => {
    const counterCssPath = resolve(
      repoRoot,
      'packages/frontend-demo/resources/css/components/counter.css',
    );
    const content = readFileSync(counterCssPath, 'utf-8');
    expect(content).not.toContain('var(--color-text)');
    // Should now reference --mk-color-on-surface
    expect(content).toContain('--mk-color-on-surface');
  });
});
