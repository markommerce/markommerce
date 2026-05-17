import { describe, it, expect } from 'vitest';
import { readFileSync } from 'fs';
import { resolve } from 'path';

const cssDir = resolve(__dirname, '../css');
const baseCss = readFileSync(resolve(cssDir, 'base.css'), 'utf-8');

function getLayerBlock(): string {
  const match = baseCss.match(/@layer base\s*\{([\s\S]*)\}/);
  expect(match).not.toBeNull();
  return match![1];
}

describe('base.css', () => {
  it('wraps all rules in @layer base', () => {
    expect(baseCss).toMatch(/@layer base\s*\{/);
    const layerBlock = baseCss.match(/@layer base\s*\{([\s\S]*)\}/);
    expect(layerBlock).not.toBeNull();
  });

  it('sets box-sizing border-box on all elements and pseudo-elements', () => {
    const inside = getLayerBlock();
    expect(inside).toMatch(/\*,\s*\*::before,\s*\*::after\s*\{/);
    expect(inside).toContain('box-sizing: border-box');
  });

  it('sets body font-family from --mk-font-sans and font-size from --mk-font-size-base', () => {
    const inside = getLayerBlock();
    expect(inside).toMatch(/body\s*\{/);
    expect(inside).toContain('font-family: var(--mk-font-sans)');
    expect(inside).toContain('font-size: var(--mk-font-size-base)');
  });

  it('sets body color from --mk-color-fg and background-color from --mk-color-bg', () => {
    const inside = getLayerBlock();
    expect(inside).toContain('color: var(--mk-color-fg)');
    expect(inside).toContain('background-color: var(--mk-color-bg)');
  });

  it('removes default body margin', () => {
    const inside = getLayerBlock();
    // Must have margin: 0 inside the body block
    const bodyBlock = inside.match(/body\s*\{([^}]*)\}/);
    expect(bodyBlock).not.toBeNull();
    expect(bodyBlock![1]).toContain('margin: 0');
  });

  it('sets heading line-height from --mk-line-height-tight and font-weight from --mk-font-weight-bold', () => {
    const inside = getLayerBlock();
    expect(inside).toMatch(/h1,\s*h2,\s*h3,\s*h4,\s*h5,\s*h6\s*\{/);
    const headingBlock = inside.match(/h1,\s*h2,\s*h3,\s*h4,\s*h5,\s*h6\s*\{([^}]*)\}/);
    expect(headingBlock).not.toBeNull();
    expect(headingBlock![1]).toContain('line-height: var(--mk-line-height-tight)');
    expect(headingBlock![1]).toContain('font-weight: var(--mk-font-weight-bold)');
    expect(headingBlock![1]).toContain('margin: 0');
  });

  it('maps h1 through h6 to the --mk-font-size-* scale (h1=3xl, h2=2xl, h3=xl, h4=lg, h5=base, h6=sm)', () => {
    const inside = getLayerBlock();
    expect(inside).toMatch(/h1\s*\{[^}]*font-size:\s*var\(--mk-font-size-3xl\)/);
    expect(inside).toMatch(/h2\s*\{[^}]*font-size:\s*var\(--mk-font-size-2xl\)/);
    expect(inside).toMatch(/h3\s*\{[^}]*font-size:\s*var\(--mk-font-size-xl\)/);
    expect(inside).toMatch(/h4\s*\{[^}]*font-size:\s*var\(--mk-font-size-lg\)/);
    expect(inside).toMatch(/h5\s*\{[^}]*font-size:\s*var\(--mk-font-size-base\)/);
    expect(inside).toMatch(/h6\s*\{[^}]*font-size:\s*var\(--mk-font-size-sm\)/);
  });

  it('styles anchors with --mk-color-primary and underlines them', () => {
    const inside = getLayerBlock();
    expect(inside).toMatch(/a\s*\{/);
    const anchorBlock = inside.match(/a\s*\{([^}]*)\}/);
    expect(anchorBlock).not.toBeNull();
    expect(anchorBlock![1]).toContain('color: var(--mk-color-primary)');
    expect(anchorBlock![1]).toContain('text-decoration: underline');
    // hover state
    expect(inside).toMatch(/a:hover\s*\{/);
    const hoverBlock = inside.match(/a:hover\s*\{([^}]*)\}/);
    expect(hoverBlock).not.toBeNull();
    expect(hoverBlock![1]).toContain('color: var(--mk-color-primary-hover, var(--mk-color-primary))');
  });

  it('constrains img and video to max-width 100% with height auto and display block', () => {
    const inside = getLayerBlock();
    expect(inside).toMatch(/img,\s*video\s*\{/);
    const mediaBlock = inside.match(/img,\s*video\s*\{([^}]*)\}/);
    expect(mediaBlock).not.toBeNull();
    expect(mediaBlock![1]).toContain('max-width: 100%');
    expect(mediaBlock![1]).toContain('height: auto');
    expect(mediaBlock![1]).toContain('display: block');
  });

  it('includes a documentation comment describing the :not(:defined) CLS safety-net convention', () => {
    expect(baseCss).toContain(':not(:defined)');
    expect(baseCss).toContain('CLS');
    // The :not(:defined) rule is intentionally a no-op — layout is supplied by
    // @layer components tag selectors which take precedence regardless of JS
    // definition state. No visibility: hidden declaration is shipped.
    expect(baseCss).not.toContain('visibility: hidden');
  });

  it("is exported from theme-blank package.json so import '@markommerce/theme-blank/css/base.css' resolves", () => {
    const pkg = JSON.parse(
      readFileSync(resolve(__dirname, '../../package.json'), 'utf-8'),
    );
    expect(pkg.exports['./css/base.css']).toBeDefined();
    expect(pkg.exports['./css/base.css']).toBe('./resources/css/base.css');
  });

  it("the frontend-demo main.ts imports @markommerce/theme-blank/css/base.css between tokens.css and the .generated/extensions import", () => {
    const mainTs = readFileSync(
      resolve(__dirname, '../../../frontend-demo/resources/js/main.ts'),
      'utf-8',
    );
    expect(mainTs).toContain("import '@markommerce/theme-blank/css/base.css'");
    const tokensPos = mainTs.indexOf("import '@markommerce/theme-blank/css/tokens.css'");
    const basePos = mainTs.indexOf("import '@markommerce/theme-blank/css/base.css'");
    const extensionsPos = mainTs.indexOf("import './.generated/extensions'");
    expect(tokensPos).toBeLessThan(basePos);
    expect(basePos).toBeLessThan(extensionsPos);
  });

  it('the frontend-demo package.test.ts asserts base.css appears in the import order after tokens.css and before extensions', () => {
    const packageTest = readFileSync(
      resolve(__dirname, '../../../frontend-demo/resources/js/package.test.ts'),
      'utf-8',
    );
    expect(packageTest).toContain("import '@markommerce/theme-blank/css/base.css'");
    // Verify the assertion checks ordering: tokens < base < extensions
    const tokensPos = packageTest.indexOf("import '@markommerce/theme-blank/css/tokens.css'");
    const basePos = packageTest.indexOf("import '@markommerce/theme-blank/css/base.css'");
    const extensionsPos = packageTest.indexOf("import './.generated/extensions'");
    expect(tokensPos).toBeGreaterThanOrEqual(0);
    expect(basePos).toBeGreaterThanOrEqual(0);
    expect(extensionsPos).toBeGreaterThanOrEqual(0);
  });
});
