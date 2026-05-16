import { describe, it, expect } from 'vitest';
import { readFileSync } from 'fs';
import { resolve } from 'path';

const cssDir = resolve(__dirname, '../css');
const tokensCss = readFileSync(resolve(cssDir, 'tokens.css'), 'utf-8');

function getLayerBlock(): string {
  const match = tokensCss.match(/@layer tokens\s*\{([\s\S]*)\}/);
  expect(match).not.toBeNull();
  return match![1];
}

function getDarkBlock(): string {
  const match = tokensCss.match(/\[data-theme="dark"\]\s*\{([\s\S]*?)\}/);
  expect(match).not.toBeNull();
  return match![1];
}

describe('tokens-expanded.css', () => {
  it('exposes --mk-color-fg, --mk-color-bg, --mk-color-fg-muted as semantic foreground/background tokens', () => {
    const inside = getLayerBlock();
    expect(inside).toContain('--mk-color-fg:');
    expect(inside).toContain('--mk-color-bg:');
    expect(inside).toContain('--mk-color-fg-muted:');
  });

  it('exposes --mk-color-success, --mk-color-warning, --mk-color-danger, --mk-color-info status colors', () => {
    const inside = getLayerBlock();
    expect(inside).toContain('--mk-color-success:');
    expect(inside).toContain('--mk-color-warning:');
    expect(inside).toContain('--mk-color-danger:');
    expect(inside).toContain('--mk-color-info:');
  });

  it('exposes --mk-color-primary-hover and --mk-color-primary-active variants', () => {
    const inside = getLayerBlock();
    expect(inside).toContain('--mk-color-primary-hover:');
    expect(inside).toContain('--mk-color-primary-active:');
  });

  it('exposes --mk-space-0 as literal 0 (not mapped to an Open Props var) and --mk-space-6 through --mk-space-9 mapped to --size-6 through --size-9', () => {
    const inside = getLayerBlock();
    expect(inside).toMatch(/--mk-space-0:\s*0\s*;/);
    expect(inside).toContain('--mk-space-6: var(--size-6)');
    expect(inside).toContain('--mk-space-7: var(--size-7)');
    expect(inside).toContain('--mk-space-8: var(--size-8)');
    expect(inside).toContain('--mk-space-9: var(--size-9)');
  });

  it('exposes --mk-font-sans and --mk-font-mono font-family tokens', () => {
    const inside = getLayerBlock();
    expect(inside).toContain('--mk-font-sans:');
    expect(inside).toContain('--mk-font-mono:');
  });

  it('exposes --mk-font-size-xs, --mk-font-size-2xl, --mk-font-size-3xl (extending the sm–xl range from task 002)', () => {
    const inside = getLayerBlock();
    expect(inside).toContain('--mk-font-size-xs:');
    expect(inside).toContain('--mk-font-size-2xl:');
    expect(inside).toContain('--mk-font-size-3xl:');
  });

  it('exposes --mk-line-height-tight, --mk-line-height-normal, --mk-line-height-loose', () => {
    const inside = getLayerBlock();
    expect(inside).toContain('--mk-line-height-tight:');
    expect(inside).toContain('--mk-line-height-normal:');
    expect(inside).toContain('--mk-line-height-loose:');
  });

  it('exposes --mk-font-weight-medium', () => {
    const inside = getLayerBlock();
    expect(inside).toContain('--mk-font-weight-medium:');
  });

  it('exposes --mk-radius-full', () => {
    const inside = getLayerBlock();
    expect(inside).toContain('--mk-radius-full:');
  });

  it('exposes --mk-shadow-sm, --mk-shadow-md, --mk-shadow-lg', () => {
    const inside = getLayerBlock();
    expect(inside).toContain('--mk-shadow-sm:');
    expect(inside).toContain('--mk-shadow-md:');
    expect(inside).toContain('--mk-shadow-lg:');
  });

  it('exposes --mk-duration-normal, --mk-duration-slow, --mk-ease-out, --mk-ease-in-out', () => {
    const inside = getLayerBlock();
    expect(inside).toContain('--mk-duration-normal:');
    expect(inside).toContain('--mk-duration-slow:');
    expect(inside).toContain('--mk-ease-out:');
    expect(inside).toContain('--mk-ease-in-out:');
  });

  it('declares @custom-media --mk-breakpoint-sm, --mk-breakpoint-md, --mk-breakpoint-lg, --mk-breakpoint-xl at the top level (outside @layer tokens)', () => {
    expect(tokensCss).toContain('@custom-media --mk-breakpoint-sm');
    expect(tokensCss).toContain('@custom-media --mk-breakpoint-md');
    expect(tokensCss).toContain('@custom-media --mk-breakpoint-lg');
    expect(tokensCss).toContain('@custom-media --mk-breakpoint-xl');
    // Ensure these are outside the @layer tokens block — find their positions.
    // Use the opening brace form to skip any mention in comments.
    const layerStart = tokensCss.indexOf('@layer tokens {');
    const smPos = tokensCss.indexOf('@custom-media --mk-breakpoint-sm');
    const mdPos = tokensCss.indexOf('@custom-media --mk-breakpoint-md');
    const lgPos = tokensCss.indexOf('@custom-media --mk-breakpoint-lg');
    const xlPos = tokensCss.indexOf('@custom-media --mk-breakpoint-xl');
    expect(smPos).toBeLessThan(layerStart);
    expect(mdPos).toBeLessThan(layerStart);
    expect(lgPos).toBeLessThan(layerStart);
    expect(xlPos).toBeLessThan(layerStart);
  });

  it('rebinds --mk-color-fg and --mk-color-bg inside [data-theme="dark"]', () => {
    const inside = getDarkBlock();
    expect(inside).toContain('--mk-color-fg:');
    expect(inside).toContain('--mk-color-bg:');
  });

  it('keeps the migrated task-002 tokens intact (regression guard)', () => {
    const inside = getLayerBlock();
    // Colors from task 002
    expect(inside).toContain('--mk-color-primary:');
    expect(inside).toContain('--mk-color-primary-light:');
    expect(inside).toContain('--mk-color-on-primary:');
    expect(inside).toContain('--mk-color-surface:');
    expect(inside).toContain('--mk-color-on-surface:');
    expect(inside).toContain('--mk-color-border:');
    expect(inside).toContain('--mk-color-error:');
    // Spacing from task 002
    expect(inside).toContain('--mk-space-1:');
    expect(inside).toContain('--mk-space-2:');
    expect(inside).toContain('--mk-space-3:');
    expect(inside).toContain('--mk-space-4:');
    expect(inside).toContain('--mk-space-5:');
    // Typography from task 002
    expect(inside).toContain('--mk-font-size-sm:');
    expect(inside).toContain('--mk-font-size-base:');
    expect(inside).toContain('--mk-font-size-lg:');
    expect(inside).toContain('--mk-font-size-xl:');
    expect(inside).toContain('--mk-font-weight-normal:');
    expect(inside).toContain('--mk-font-weight-bold:');
    // Transitions from task 002
    expect(inside).toContain('--mk-transition-fast:');
    expect(inside).toContain('--mk-transition-base:');
    // Radii from task 002
    expect(inside).toContain('--mk-radius-sm:');
    expect(inside).toContain('--mk-radius-base:');
    expect(inside).toContain('--mk-radius-lg:');
  });
});
