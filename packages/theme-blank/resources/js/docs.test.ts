import { describe, it, expect } from 'vitest';
import { readFileSync, existsSync } from 'node:fs';
import { resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);

const repoRoot = resolve(__dirname, '../../../../');
const docsFilePath = resolve(repoRoot, 'docs/src/content/docs/packages/theme-blank/index.md');
const tokensCssPath = resolve(repoRoot, 'packages/theme-blank/resources/css/tokens.css');

let docsContent: string;
let tokensCss: string;

try {
  docsContent = readFileSync(docsFilePath, 'utf-8');
} catch {
  docsContent = '';
}

try {
  tokensCss = readFileSync(tokensCssPath, 'utf-8');
} catch {
  tokensCss = '';
}

function extractMkTokens(css: string): string[] {
  const matches = css.match(/--mk-[\w-]+(?=\s*[:{])/g) ?? [];
  // Remove duplicates and trailing colons
  return [...new Set(matches.map((t) => t.replace(/:$/, '')))];
}

describe('theme-blank docs index page', () => {
  it('ships docs/src/content/docs/packages/theme-blank/index.md', () => {
    expect(existsSync(docsFilePath)).toBe(true);
    expect(docsContent.length).toBeGreaterThan(0);
  });

  it('the frontmatter declares title "markommerce/theme-blank" and a non-empty description', () => {
    expect(docsContent).toContain('title: markommerce/theme-blank');
    const descMatch = docsContent.match(/^description:\s*(.+)$/m);
    expect(descMatch).not.toBeNull();
    expect(descMatch![1].trim().length).toBeGreaterThan(0);
  });

  it('the page contains a non-empty intro paragraph immediately after the frontmatter (no heading wrapping it)', () => {
    // After the closing ---, there should be a non-empty paragraph before the first ##
    const afterFrontmatter = docsContent.replace(/^---[\s\S]*?---\n/, '');
    const firstNonEmpty = afterFrontmatter.trimStart();
    // The first content should not start with a heading
    expect(firstNonEmpty).not.toMatch(/^##/);
    // There should be at least one non-empty line before the first ## heading
    const lines = afterFrontmatter.split('\n');
    const firstHeadingIndex = lines.findIndex((l) => l.startsWith('## '));
    const textBefore = lines.slice(0, firstHeadingIndex).join('\n').trim();
    expect(textBefore.length).toBeGreaterThan(0);
  });

  it('the page has an ## Installation section showing both composer require and npm install commands', () => {
    expect(docsContent).toContain('## Installation');
    expect(docsContent).toContain('composer require markommerce/theme-blank');
    expect(docsContent).toContain('npm install @markommerce/theme-blank');
  });

  it('the page has a ## Design Tokens section with subsections for Colors, Spacing, Typography, Radii, Shadows, Motion, and Breakpoints', () => {
    expect(docsContent).toContain('## Design Tokens');
    expect(docsContent).toContain('### Colors');
    expect(docsContent).toContain('### Spacing');
    expect(docsContent).toContain('### Typography');
    expect(docsContent).toContain('### Radii');
    expect(docsContent).toContain('### Shadows');
    expect(docsContent).toContain('### Motion');
    expect(docsContent).toContain('### Breakpoints');
  });

  it('every --mk-* token shipped in tasks 002 and 003 appears at least once in the docs page', () => {
    const tokens = extractMkTokens(tokensCss);
    expect(tokens.length).toBeGreaterThan(0);
    for (const token of tokens) {
      expect(docsContent, `expected token ${token} to appear in docs`).toContain(token);
    }
  });

  it('the page has a ## CSS Layers section explaining the cascade-layer contract', () => {
    expect(docsContent).toContain('## CSS Layers');
    // Should reference the layers involved
    expect(docsContent).toContain('tokens');
    expect(docsContent).toContain('base');
    expect(docsContent).toContain('theme');
  });

  it('the page has a ## Page Layouts section listing base, empty, 1column, 2columns-left, 2columns-right, 3columns and the blocks each exposes', () => {
    expect(docsContent).toContain('## Page Layouts');
    expect(docsContent).toContain('base');
    expect(docsContent).toContain('empty');
    expect(docsContent).toContain('1column');
    expect(docsContent).toContain('2columns-left');
    expect(docsContent).toContain('2columns-right');
    expect(docsContent).toContain('3columns');
    expect(docsContent).toContain('theme-blank::layout/');
  });

  it('the page has a ## JS API section documenting showToast, openModal, and the option/handle types, with a callout that real behavior lands in Phase 4', () => {
    expect(docsContent).toContain('## JS API');
    expect(docsContent).toContain('showToast');
    expect(docsContent).toContain('openModal');
    expect(docsContent).toContain('ToastOptions');
    expect(docsContent).toContain('ModalOptions');
    expect(docsContent).toContain('ModalHandle');
    expect(docsContent).toContain('Phase 4');
  });

  it('the page has a ## Extending the Theme section with at least one token-override example and one mixin example', () => {
    expect(docsContent).toContain('## Extending the Theme');
    // Token override example — should reference @layer theme
    expect(docsContent).toContain('@layer theme');
    // Mixin example — should reference addMixin
    expect(docsContent).toContain('addMixin');
  });

  it('the page has a ## Web Vitals section documenting the CLS-prevention architectural rule and the :not(:defined) safety net', () => {
    expect(docsContent).toContain('## Web Vitals');
    expect(docsContent).toContain('render correctly without JavaScript');
    expect(docsContent).toContain(':not(:defined)');
  });

  it('the page references the npx playwright install --with-deps chromium command for CI consumers', () => {
    expect(docsContent).toContain('npx playwright install');
  });

  it('the page contains no ## Overview heading', () => {
    expect(docsContent).not.toMatch(/^## Overview/m);
  });

  it('PHP code examples in the page use use statements rather than fully-qualified class names', () => {
    // Extract PHP code blocks
    const phpBlocks = docsContent.match(/```php[\s\S]*?```/g) ?? [];
    // If there are PHP blocks with class references (capital letters + backslash), they must have use statements
    for (const block of phpBlocks) {
      const hasFullyQualified = /\w+\\\w+/.test(block);
      if (hasFullyQualified) {
        expect(block).toContain('use ');
      }
    }
    // At minimum, if there are PHP blocks, they should follow standards
    // This test passes vacuously if there are no PHP blocks with FQCNs
    expect(true).toBe(true);
  });
});
