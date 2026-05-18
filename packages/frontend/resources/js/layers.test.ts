import { describe, it, expect } from 'vitest';
import { readFileSync } from 'fs';
import { resolve } from 'path';

const cssDir = resolve(__dirname, '../css');
const layersCss = readFileSync(resolve(cssDir, 'layers.css'), 'utf-8');

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
});
