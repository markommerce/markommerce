import { describe, it, expect } from 'vitest';
import { readFileSync } from 'fs';
import { resolve } from 'path';

const cssDir = resolve(__dirname, '../css');
const layersCss = readFileSync(resolve(cssDir, 'layers.css'), 'utf-8');
const tokensCss = readFileSync(resolve(cssDir, 'tokens.css'), 'utf-8');

describe('layers.css', () => {
  it('declares cascade layers in the order: reset, tokens, base, components, modules, theme, utilities', () => {
    expect(layersCss).toContain(
      '@layer reset, tokens, base, components, modules, theme, utilities',
    );
  });

  it('a vitest fixture that reads layers.css confirms the layer order appears as expected', () => {
    const match = layersCss.match(/@layer\s+([\w,\s]+);/);
    expect(match).not.toBeNull();
    const layers = match![1].split(',').map((l) => l.trim());
    expect(layers).toEqual([
      'reset',
      'tokens',
      'base',
      'components',
      'modules',
      'theme',
      'utilities',
    ]);
  });
});

describe('tokens.css', () => {
  it('wraps every Markommerce semantic token in @layer tokens', () => {
    expect(tokensCss).toMatch(/@layer tokens\s*\{/);
    // All custom properties should be inside @layer tokens
    const layerBlock = tokensCss.match(/@layer tokens\s*\{([\s\S]*)\}/);
    expect(layerBlock).not.toBeNull();
    const inside = layerBlock![1];
    // Should contain :root block with custom properties
    expect(inside).toMatch(/:root\s*\{/);
    expect(inside).toContain('--color-primary');
  });

  it('defines color, spacing, typography, transition, and radius semantic tokens mapped to Open Props variables', () => {
    const layerBlock = tokensCss.match(/@layer tokens\s*\{([\s\S]*)\}/);
    expect(layerBlock).not.toBeNull();
    const inside = layerBlock![1];

    // Colors
    expect(inside).toContain('--color-primary:');
    expect(inside).toContain('--color-primary-light:');
    expect(inside).toContain('--color-on-primary:');
    expect(inside).toContain('--color-surface:');
    expect(inside).toContain('--color-on-surface:');
    expect(inside).toContain('--color-border:');
    expect(inside).toContain('--color-error:');

    // Spacing
    expect(inside).toContain('--space-1:');
    expect(inside).toContain('--space-2:');
    expect(inside).toContain('--space-3:');
    expect(inside).toContain('--space-4:');
    expect(inside).toContain('--space-5:');

    // Typography
    expect(inside).toContain('--font-size-sm:');
    expect(inside).toContain('--font-size-base:');
    expect(inside).toContain('--font-size-lg:');
    expect(inside).toContain('--font-size-xl:');
    expect(inside).toContain('--font-weight-normal:');
    expect(inside).toContain('--font-weight-bold:');

    // Transitions
    expect(inside).toContain('--transition-fast:');
    expect(inside).toContain('--transition-base:');

    // Radius
    expect(inside).toContain('--radius-sm:');
    expect(inside).toContain('--radius-base:');
    expect(inside).toContain('--radius-lg:');

    // Open Props references for colors
    expect(inside).toContain('var(--blue-6)');
    expect(inside).toContain('var(--blue-4)');
    expect(inside).toContain('var(--gray-0)');
    expect(inside).toContain('var(--gray-9)');
    expect(inside).toContain('var(--gray-3)');
    expect(inside).toContain('var(--red-6)');

    // Open Props references for spacing
    expect(inside).toContain('var(--size-1)');
    expect(inside).toContain('var(--size-2)');
    expect(inside).toContain('var(--size-3)');
    expect(inside).toContain('var(--size-4)');
    expect(inside).toContain('var(--size-5)');

    // Open Props references for typography
    expect(inside).toContain('var(--font-size-0)');
    expect(inside).toContain('var(--font-size-1)');
    expect(inside).toContain('var(--font-size-2)');
    expect(inside).toContain('var(--font-size-3)');

    // Open Props references for radius
    expect(inside).toContain('var(--radius-2)');
    expect(inside).toContain('var(--radius-3)');
    expect(inside).toContain('var(--radius-4)');
  });

  it('provides a dark-mode override block keyed on [data-theme="dark"]', () => {
    expect(tokensCss).toContain('[data-theme="dark"]');
    const darkBlock = tokensCss.match(/\[data-theme="dark"\]\s*\{([\s\S]*?)\}/);
    expect(darkBlock).not.toBeNull();
    const inside = darkBlock![1];
    expect(inside).toContain('--color-primary:');
    expect(inside).toContain('--color-surface:');
    expect(inside).toContain('--color-on-surface:');
    expect(inside).toContain('--color-border:');
  });
});

describe('package.json exports', () => {
  it('is exported from package.json so import \'@markommerce/frontend/css/layers.css\' resolves', () => {
    const pkg = JSON.parse(
      readFileSync(
        resolve(__dirname, '../../package.json'),
        'utf-8',
      ),
    );
    expect(pkg.exports['./css/layers.css']).toBeDefined();
    expect(pkg.exports['./css/layers.css']).toBe('./resources/css/layers.css');
  });

  it('is exported from package.json so import \'@markommerce/frontend/css/tokens.css\' resolves', () => {
    const pkg = JSON.parse(
      readFileSync(
        resolve(__dirname, '../../package.json'),
        'utf-8',
      ),
    );
    expect(pkg.exports['./css/tokens.css']).toBeDefined();
    expect(pkg.exports['./css/tokens.css']).toBe('./resources/css/tokens.css');
  });
});
