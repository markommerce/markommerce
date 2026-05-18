import { describe, it, expect } from 'vitest';
import { readFileSync } from 'fs';
import { resolve } from 'path';

const cssDir = resolve(__dirname, '../css');
const tokensCss = readFileSync(resolve(cssDir, 'tokens.css'), 'utf-8');

describe('tokens.css', () => {
  it('wraps every Markommerce semantic token in @layer tokens', () => {
    expect(tokensCss).toMatch(/@layer tokens\s*\{/);
    const layerBlock = tokensCss.match(/@layer tokens\s*\{([\s\S]*)\}/);
    expect(layerBlock).not.toBeNull();
    const inside = layerBlock![1];
    expect(inside).toMatch(/:root\s*\{/);
    expect(inside).toContain('--mk-color-primary');
  });

  it('exposes the migrated --mk-color-primary, --mk-color-primary-light, --mk-color-on-primary, --mk-color-surface, --mk-color-on-surface, --mk-color-border, --mk-color-error tokens', () => {
    const layerBlock = tokensCss.match(/@layer tokens\s*\{([\s\S]*)\}/);
    expect(layerBlock).not.toBeNull();
    const inside = layerBlock![1];
    expect(inside).toContain('--mk-color-primary:');
    expect(inside).toContain('--mk-color-primary-light:');
    expect(inside).toContain('--mk-color-on-primary:');
    expect(inside).toContain('--mk-color-surface:');
    expect(inside).toContain('--mk-color-on-surface:');
    expect(inside).toContain('--mk-color-border:');
    expect(inside).toContain('--mk-color-error:');
  });

  it('exposes the migrated --mk-space-1 through --mk-space-5 tokens', () => {
    const layerBlock = tokensCss.match(/@layer tokens\s*\{([\s\S]*)\}/);
    expect(layerBlock).not.toBeNull();
    const inside = layerBlock![1];
    expect(inside).toContain('--mk-space-1:');
    expect(inside).toContain('--mk-space-2:');
    expect(inside).toContain('--mk-space-3:');
    expect(inside).toContain('--mk-space-4:');
    expect(inside).toContain('--mk-space-5:');
  });

  it('exposes the migrated --mk-font-size-sm, --mk-font-size-base, --mk-font-size-lg, --mk-font-size-xl tokens', () => {
    const layerBlock = tokensCss.match(/@layer tokens\s*\{([\s\S]*)\}/);
    expect(layerBlock).not.toBeNull();
    const inside = layerBlock![1];
    expect(inside).toContain('--mk-font-size-sm:');
    expect(inside).toContain('--mk-font-size-base:');
    expect(inside).toContain('--mk-font-size-lg:');
    expect(inside).toContain('--mk-font-size-xl:');
  });

  it('exposes the migrated --mk-font-weight-normal and --mk-font-weight-bold tokens', () => {
    const layerBlock = tokensCss.match(/@layer tokens\s*\{([\s\S]*)\}/);
    expect(layerBlock).not.toBeNull();
    const inside = layerBlock![1];
    expect(inside).toContain('--mk-font-weight-normal:');
    expect(inside).toContain('--mk-font-weight-bold:');
  });

  it('exposes the migrated --mk-transition-fast and --mk-transition-base tokens', () => {
    const layerBlock = tokensCss.match(/@layer tokens\s*\{([\s\S]*)\}/);
    expect(layerBlock).not.toBeNull();
    const inside = layerBlock![1];
    expect(inside).toContain('--mk-transition-fast:');
    expect(inside).toContain('--mk-transition-base:');
  });

  it('exposes the migrated --mk-radius-sm, --mk-radius-base, --mk-radius-lg tokens', () => {
    const layerBlock = tokensCss.match(/@layer tokens\s*\{([\s\S]*)\}/);
    expect(layerBlock).not.toBeNull();
    const inside = layerBlock![1];
    expect(inside).toContain('--mk-radius-sm:');
    expect(inside).toContain('--mk-radius-base:');
    expect(inside).toContain('--mk-radius-lg:');
  });

  it('rebinds the appropriate --mk-color-* tokens inside [data-theme="dark"]', () => {
    expect(tokensCss).toContain('[data-theme="dark"]');
    const darkBlock = tokensCss.match(/\[data-theme="dark"\]\s*\{([\s\S]*?)\}/);
    expect(darkBlock).not.toBeNull();
    const inside = darkBlock![1];
    expect(inside).toContain('--mk-color-primary:');
    expect(inside).toContain('--mk-color-surface:');
    expect(inside).toContain('--mk-color-on-surface:');
    expect(inside).toContain('--mk-color-border:');
  });

  it('is exported from theme-blank package.json so import \'@markommerce/theme-blank/css/tokens.css\' resolves', () => {
    const pkg = JSON.parse(
      readFileSync(
        resolve(__dirname, '../../package.json'),
        'utf-8',
      ),
    );
    expect(pkg.exports['./css/tokens.css']).toBeDefined();
    expect(pkg.exports['./css/tokens.css']).toBe('./resources/css/tokens.css');
  });

  it('declares all 10 mk-alert tokens inside :root', () => {
    const layerBlock = tokensCss.match(/@layer tokens\s*\{([\s\S]*)\}/);
    expect(layerBlock).not.toBeNull();
    const rootBlock = layerBlock![1].match(/:root\s*\{([\s\S]*?)\}/);
    expect(rootBlock).not.toBeNull();
    const inside = rootBlock![1];
    expect(inside).toContain('--mk-alert-bg-info:');
    expect(inside).toContain('--mk-alert-bg-success:');
    expect(inside).toContain('--mk-alert-bg-warning:');
    expect(inside).toContain('--mk-alert-bg-danger:');
    expect(inside).toContain('--mk-alert-fg-info:');
    expect(inside).toContain('--mk-alert-fg-success:');
    expect(inside).toContain('--mk-alert-fg-warning:');
    expect(inside).toContain('--mk-alert-fg-danger:');
    expect(inside).toContain('--mk-alert-padding:');
    expect(inside).toContain('--mk-alert-radius:');
  });

  it('declares all 4 mk-alert-border-color tokens inside :root', () => {
    const layerBlock = tokensCss.match(/@layer tokens\s*\{([\s\S]*)\}/);
    expect(layerBlock).not.toBeNull();
    const rootBlock = layerBlock![1].match(/:root\s*\{([\s\S]*?)\}/);
    expect(rootBlock).not.toBeNull();
    const inside = rootBlock![1];
    expect(inside).toContain('--mk-alert-border-color-info:');
    expect(inside).toContain('--mk-alert-border-color-success:');
    expect(inside).toContain('--mk-alert-border-color-warning:');
    expect(inside).toContain('--mk-alert-border-color-danger:');
  });

  it('declares all 9 mk-toast tokens inside :root', () => {
    const layerBlock = tokensCss.match(/@layer tokens\s*\{([\s\S]*)\}/);
    expect(layerBlock).not.toBeNull();
    const rootBlock = layerBlock![1].match(/:root\s*\{([\s\S]*?)\}/);
    expect(rootBlock).not.toBeNull();
    const inside = rootBlock![1];
    expect(inside).toContain('--mk-toast-bg:');
    expect(inside).toContain('--mk-toast-fg:');
    expect(inside).toContain('--mk-toast-region-gap:');
    expect(inside).toContain('--mk-toast-region-inset:');
    expect(inside).toContain('--mk-toast-shadow:');
    expect(inside).toContain('--mk-toast-radius:');
    expect(inside).toContain('--mk-toast-padding:');
    expect(inside).toContain('--mk-toast-min-width:');
    expect(inside).toContain('--mk-toast-max-width:');
  });

  it('declares all 7 mk-modal tokens inside :root', () => {
    const layerBlock = tokensCss.match(/@layer tokens\s*\{([\s\S]*)\}/);
    expect(layerBlock).not.toBeNull();
    const rootBlock = layerBlock![1].match(/:root\s*\{([\s\S]*?)\}/);
    expect(rootBlock).not.toBeNull();
    const inside = rootBlock![1];
    expect(inside).toContain('--mk-modal-bg:');
    expect(inside).toContain('--mk-modal-fg:');
    expect(inside).toContain('--mk-modal-radius:');
    expect(inside).toContain('--mk-modal-padding:');
    expect(inside).toContain('--mk-modal-shadow:');
    expect(inside).toContain('--mk-modal-backdrop-color:');
    expect(inside).toContain('--mk-modal-width-sm:');
    expect(inside).toContain('--mk-modal-width-md:');
    expect(inside).toContain('--mk-modal-width-lg:');
  });

  it('declares all 5 mk-drawer tokens inside :root', () => {
    const layerBlock = tokensCss.match(/@layer tokens\s*\{([\s\S]*)\}/);
    expect(layerBlock).not.toBeNull();
    const rootBlock = layerBlock![1].match(/:root\s*\{([\s\S]*?)\}/);
    expect(rootBlock).not.toBeNull();
    const inside = rootBlock![1];
    expect(inside).toContain('--mk-drawer-bg:');
    expect(inside).toContain('--mk-drawer-fg:');
    expect(inside).toContain('--mk-drawer-width-sm:');
    expect(inside).toContain('--mk-drawer-width-md:');
    expect(inside).toContain('--mk-drawer-width-lg:');
    expect(inside).toContain('--mk-drawer-shadow:');
    expect(inside).toContain('--mk-drawer-padding:');
  });

  it('declares all 4 mk-spinner token groups inside :root', () => {
    const layerBlock = tokensCss.match(/@layer tokens\s*\{([\s\S]*)\}/);
    expect(layerBlock).not.toBeNull();
    const rootBlock = layerBlock![1].match(/:root\s*\{([\s\S]*?)\}/);
    expect(rootBlock).not.toBeNull();
    const inside = rootBlock![1];
    expect(inside).toContain('--mk-spinner-size-sm:');
    expect(inside).toContain('--mk-spinner-size-base:');
    expect(inside).toContain('--mk-spinner-size-lg:');
    expect(inside).toContain('--mk-spinner-thickness:');
    expect(inside).toContain('--mk-spinner-color:');
    expect(inside).toContain('--mk-spinner-duration:');
  });

  it('declares all 4 mk-skeleton tokens inside :root', () => {
    const layerBlock = tokensCss.match(/@layer tokens\s*\{([\s\S]*)\}/);
    expect(layerBlock).not.toBeNull();
    const rootBlock = layerBlock![1].match(/:root\s*\{([\s\S]*?)\}/);
    expect(rootBlock).not.toBeNull();
    const inside = rootBlock![1];
    expect(inside).toContain('--mk-skeleton-bg:');
    expect(inside).toContain('--mk-skeleton-shimmer-color:');
    expect(inside).toContain('--mk-skeleton-radius:');
    expect(inside).toContain('--mk-skeleton-duration:');
  });

  it('overrides at least 3 feedback tokens inside [data-theme="dark"]', () => {
    expect(tokensCss).toContain('[data-theme="dark"]');
    const darkBlock = tokensCss.match(/\[data-theme="dark"\]\s*\{([\s\S]*?)\}/);
    expect(darkBlock).not.toBeNull();
    const inside = darkBlock![1];
    expect(inside).toContain('--mk-toast-bg:');
    expect(inside).toContain('--mk-modal-bg:');
    expect(inside).toContain('--mk-skeleton-bg:');
  });
});
