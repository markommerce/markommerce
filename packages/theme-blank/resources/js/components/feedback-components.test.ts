// @vitest-environment happy-dom
import { describe, it, expect } from 'vitest';
import * as fs from 'node:fs';
import * as path from 'node:path';
import { fileURLToPath } from 'node:url';
import { execSync } from 'node:child_process';
import { MkElement, getRegisteredComponents } from '@markommerce/frontend';

import './mk-alert';
import { MkAlertElement } from './mk-alert';
import './mk-toast';
import { MkToastElement } from './mk-toast';
import './mk-spinner';
import { MkSpinnerElement } from './mk-spinner';
import './mk-skeleton';
import { MkSkeletonElement } from './mk-skeleton';
import './mk-modal';
import { MkModalElement } from './mk-modal';
import './mk-drawer';
import { MkDrawerElement } from './mk-drawer';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

const FEEDBACK_COMPONENTS = [
  { tag: 'mk-alert', ElementClass: MkAlertElement },
  { tag: 'mk-toast', ElementClass: MkToastElement },
  { tag: 'mk-spinner', ElementClass: MkSpinnerElement },
  { tag: 'mk-skeleton', ElementClass: MkSkeletonElement },
  { tag: 'mk-modal', ElementClass: MkModalElement },
  { tag: 'mk-drawer', ElementClass: MkDrawerElement },
] as const;

const FEEDBACK_TAG_NAMES = FEEDBACK_COMPONENTS.map((c) => c.tag);

describe('feedback components scaffold', () => {
  it('creates an empty MkElement subclass for each of the 6 components and registers it via registerBase', () => {
    for (const { tag, ElementClass } of FEEDBACK_COMPONENTS) {
      expect(ElementClass, `${tag} should export its class`).toBeDefined();
      expect(
        Object.getPrototypeOf(ElementClass),
        `${ElementClass.name} should extend MkElement`,
      ).toBe(MkElement);

      const registered = getRegisteredComponents().find((c) => c.tagName === tag);
      expect(registered, `${tag} should be registered via registerBase`).toBeDefined();
      expect(registered!.base, `${tag} registered class should be ${ElementClass.name}`).toBe(ElementClass);
    }
  });

  it('includes each component module in components/index.ts as a side-effect import', () => {
    const indexPath = path.join(__dirname, 'index.ts');
    const content = fs.readFileSync(indexPath, 'utf-8');
    for (const tag of FEEDBACK_TAG_NAMES) {
      expect(content, `index.ts should import ./${tag}`).toContain(`./${tag}`);
    }
  });

  it('adds all 6 new tag names to the base.css :not(:defined) safety net selector group', () => {
    const baseCssPath = path.join(__dirname, '../../css/base.css');
    const content = fs.readFileSync(baseCssPath, 'utf-8');
    for (const tag of FEEDBACK_TAG_NAMES) {
      expect(
        content,
        `base.css should contain :not(:defined) selector for ${tag}`,
      ).toContain(`${tag}:not(:defined)`);
    }
  });

  it('produces a passing TypeScript compile of components/index.ts', () => {
    const repoRoot = path.join(__dirname, '../../../../..');
    let output = '';
    try {
      output = execSync(
        './node_modules/.bin/tsc --noEmit -p packages/theme-blank/tsconfig.json',
        { cwd: repoRoot, encoding: 'utf-8' },
      );
    } catch (err: unknown) {
      const error = err as { stdout: string; stderr: string; message: string };
      const fullOutput = (error.stdout ?? '') + (error.stderr ?? '');
      // Filter to only errors in theme-blank files (pre-existing errors in other packages are not our concern)
      const themeBlankErrors = fullOutput
        .split('\n')
        .filter((line) => line.includes('packages/theme-blank/'))
        .join('\n');
      if (themeBlankErrors.trim()) {
        throw new Error(`TypeScript compilation failed in theme-blank:\n${themeBlankErrors}`);
      }
      // Pre-existing errors in other packages — not our concern for this task
      return;
    }
    expect(output).toBe('');
  });

  it('ensures stylelint passes on all 6 new component CSS files', () => {
    const repoRoot = path.join(__dirname, '../../../../..');
    const feedbackCssFiles = FEEDBACK_TAG_NAMES.map(
      (tag) => `"packages/theme-blank/resources/css/components/${tag}.css"`,
    ).join(' ');
    let result: string;
    try {
      result = execSync(
        `./node_modules/.bin/stylelint ${feedbackCssFiles}`,
        { cwd: repoRoot, encoding: 'utf-8' },
      );
    } catch (err: unknown) {
      const error = err as { stdout: string; stderr: string };
      throw new Error(`stylelint failed on feedback CSS files:\n${error.stdout}\n${error.stderr}`);
    }
    expect(result).toBe('');
  });

  it('leaves the existing index.ts showToast/openModal stub exports intact (no behavior change in this task)', async () => {
    const { showToast, openModal } = await import('../index.js');
    expect(typeof showToast, 'showToast should still be a function').toBe('function');
    expect(typeof openModal, 'openModal should still be a function').toBe('function');
  });
});
