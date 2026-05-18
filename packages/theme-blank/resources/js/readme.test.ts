import { describe, it, expect } from 'vitest';
import { readFileSync, existsSync } from 'node:fs';
import { resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);

const repoRoot = resolve(__dirname, '../../../../');
const readmePath = resolve(repoRoot, 'packages/theme-blank/README.md');

let readmeContent: string;

try {
  readmeContent = readFileSync(readmePath, 'utf-8');
} catch {
  readmeContent = '';
}

const readmeLines = readmeContent.split('\n');

describe('packages/theme-blank/README.md', () => {
  it('it ships packages/theme-blank/README.md', () => {
    expect(existsSync(readmePath)).toBe(true);
    expect(readmeContent.length).toBeGreaterThan(0);
  });

  it('the README starts with "# markommerce/theme-blank" as the only h1', () => {
    expect(readmeLines[0]).toBe('# markommerce/theme-blank');
    const h1Lines = readmeLines.filter((line) => line.startsWith('# '));
    expect(h1Lines).toHaveLength(1);
  });

  it('the README has a one-line description immediately after the title', () => {
    // Line 0 is the h1, line 1 should be blank, line 2 should be the description
    expect(readmeLines[1]).toBe('');
    const descriptionLine = readmeLines[2];
    expect(descriptionLine.trim().length).toBeGreaterThan(0);
    expect(descriptionLine).not.toMatch(/^#/);
  });

  it('the README has an ## Installation section with both composer require markommerce/theme-blank and npm install @markommerce/theme-blank commands', () => {
    expect(readmeContent).toContain('## Installation');
    expect(readmeContent).toContain('composer require markommerce/theme-blank');
    expect(readmeContent).toContain('npm install @markommerce/theme-blank');
  });

  it('the README has a ## Quick Example section with at least one code block', () => {
    expect(readmeContent).toContain('## Quick Example');
    const quickExampleIndex = readmeContent.indexOf('## Quick Example');
    const afterQuickExample = readmeContent.slice(quickExampleIndex);
    expect(afterQuickExample).toContain('```');
  });

  it('the README has a ## Documentation section linking to https://markommerce.dev/docs/packages/theme-blank/', () => {
    expect(readmeContent).toContain('## Documentation');
    expect(readmeContent).toContain('https://markommerce.dev/docs/packages/theme-blank/');
  });

  it('the README is under 60 lines (slim-format guard)', () => {
    expect(readmeLines.length).toBeLessThan(60);
  });

  it('the README does not duplicate the full content of the docs page (no token tables)', () => {
    expect(readmeContent).not.toContain('| --mk-color');
  });
});
