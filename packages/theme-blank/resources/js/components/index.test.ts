// @vitest-environment happy-dom
import { describe, it, expect, beforeAll } from 'vitest';
import * as fs from 'node:fs';
import * as path from 'node:path';
import { fileURLToPath } from 'node:url';
import { execSync } from 'node:child_process';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

const TAG_NAMES = [
  'mk-stack',
  'mk-cluster',
  'mk-grid',
  'mk-container',
  'mk-sidebar',
  'mk-switcher',
  'mk-cover',
  'mk-divider',
  'mk-heading',
  'mk-text',
  'mk-link',
  'mk-badge',
] as const;

const CLASS_NAMES = [
  'MkStackElement',
  'MkClusterElement',
  'MkGridElement',
  'MkContainerElement',
  'MkSidebarElement',
  'MkSwitcherElement',
  'MkCoverElement',
  'MkDividerElement',
  'MkHeadingElement',
  'MkTextElement',
  'MkLinkElement',
  'MkBadgeElement',
] as const;

describe('components/index', () => {
  it('ships packages/theme-blank/resources/js/components/index.ts that imports all 12 primitive modules', () => {
    const indexPath = path.join(__dirname, 'index.ts');
    expect(fs.existsSync(indexPath)).toBe(true);
    const content = fs.readFileSync(indexPath, 'utf-8');
    for (const tag of TAG_NAMES) {
      expect(content).toContain(`./${tag}`);
    }
  });

  it('scaffolds 12 stub component .ts files, each importing its matching .css file and calling registerBase with the correct tag name', () => {
    for (const tag of TAG_NAMES) {
      const filePath = path.join(__dirname, `${tag}.ts`);
      expect(fs.existsSync(filePath), `${tag}.ts should exist`).toBe(true);
      const content = fs.readFileSync(filePath, 'utf-8');
      expect(content, `${tag}.ts should import its CSS`).toContain(
        `../../css/components/${tag}.css`,
      );
      expect(content, `${tag}.ts should call registerBase`).toContain(
        `registerBase('${tag}'`,
      );
    }
  });

  it('scaffolds 12 empty .css files under packages/theme-blank/resources/css/components/, each containing only the @layer components block', () => {
    const cssDir = path.join(__dirname, '../../css/components');
    for (const tag of TAG_NAMES) {
      const filePath = path.join(cssDir, `${tag}.css`);
      expect(fs.existsSync(filePath), `${tag}.css should exist`).toBe(true);
      const content = fs.readFileSync(filePath, 'utf-8');
      expect(content, `${tag}.css should contain @layer components`).toContain('@layer components');
    }
  });

  describe('importing @markommerce/theme-blank registers all 12 primitive tags via the mixin registry', () => {
    it('importing @markommerce/theme-blank registers all 12 primitive tags via the mixin registry (assert with getRegisteredComponents() from @markommerce/frontend)', async () => {
      await import('@markommerce/theme-blank');
      const { getRegisteredComponents } = await import('@markommerce/frontend');
      const registered = getRegisteredComponents();
      const registeredTags = registered.map((c) => c.tagName);
      for (const tag of TAG_NAMES) {
        expect(registeredTags, `${tag} should be registered`).toContain(tag);
      }
    });
  });

  describe('each registered class extends MkElement', () => {
    beforeAll(async () => {
      await import('@markommerce/theme-blank');
      const { defineAllComponents } = await import('@markommerce/frontend');
      for (const tag of TAG_NAMES) {
        if (!customElements.get(tag)) {
          // defineAllComponents will define only those not yet defined
        }
      }
      defineAllComponents();
    });

    it('each registered class extends MkElement (assert via instanceof MkElement after calling defineAllComponents())', async () => {
      const { MkElement } = await import('@markommerce/frontend');
      for (const tag of TAG_NAMES) {
        const el = document.createElement(tag);
        expect(el instanceof MkElement, `${tag} element should be instanceof MkElement`).toBe(true);
      }
    });
  });

  it('packages/theme-blank/resources/css/base.css contains a :not(:defined) selector group listing every Phase 2 tag name', () => {
    const baseCssPath = path.join(__dirname, '../../css/base.css');
    const content = fs.readFileSync(baseCssPath, 'utf-8');
    for (const tag of TAG_NAMES) {
      expect(content, `base.css should contain :not(:defined) selector for ${tag}`).toContain(
        `${tag}:not(:defined)`,
      );
    }
  });

  it('the safety-net rule lives inside the @layer base block', () => {
    const baseCssPath = path.join(__dirname, '../../css/base.css');
    const content = fs.readFileSync(baseCssPath, 'utf-8');
    // Check that :not(:defined) appears within @layer base
    const layerBaseStart = content.indexOf('@layer base');
    const notDefinedIdx = content.indexOf(':not(:defined)');
    expect(layerBaseStart, '@layer base should exist').toBeGreaterThan(-1);
    expect(notDefinedIdx, ':not(:defined) should exist in base.css').toBeGreaterThan(-1);
    expect(notDefinedIdx, ':not(:defined) should be after @layer base').toBeGreaterThan(layerBaseStart);
  });

  it('stylelint passes on the updated base.css (an empty declaration block under a grouped :not(:defined) selector is allowed — disable block-no-empty for that selector if stylelint-config-standard objects, OR include a no-op declaration like /* see @layer components */ comment plus a benign decl)', () => {
    const repoRoot = path.join(__dirname, '../../../../..');
    const baseCssPath = path.join(repoRoot, 'packages/theme-blank/resources/css/base.css');
    let result: string;
    try {
      result = execSync(
        `./node_modules/.bin/stylelint "${baseCssPath}"`,
        { cwd: repoRoot, encoding: 'utf-8' },
      );
    } catch (err: unknown) {
      const error = err as { stdout: string; stderr: string };
      throw new Error(`stylelint failed on base.css:\n${error.stdout}\n${error.stderr}`);
    }
    expect(result).toBe('');
  });

  it('stylelint passes on every new components/mk-*.css file (each containing only an @layer components {} block during the stub phase — disable no-empty-source for stubs OR include a /* stub */ comment)', () => {
    const repoRoot = path.join(__dirname, '../../../../..');
    const componentsCssGlob = 'packages/theme-blank/resources/css/components/*.css';
    let result: string;
    try {
      result = execSync(
        `./node_modules/.bin/stylelint "${componentsCssGlob}"`,
        { cwd: repoRoot, encoding: 'utf-8' },
      );
    } catch (err: unknown) {
      const error = err as { stdout: string; stderr: string };
      throw new Error(`stylelint failed on component CSS files:\n${error.stdout}\n${error.stderr}`);
    }
    expect(result).toBe('');
  });

  it('stylelint.config.js accepts @container at-rules (verified by adding a fixture .css file under packages/theme-blank/resources/css/components/ containing @container and running ./node_modules/.bin/stylelint on it; if it fails, add \'container\' to ignoreAtRules in stylelint.config.js)', () => {
    const repoRoot = path.join(__dirname, '../../../../..');
    const fixtureDir = path.join(repoRoot, 'packages/theme-blank/resources/css/components');
    const fixturePath = path.join(fixtureDir, '_container-fixture.css');
    fs.writeFileSync(
      fixturePath,
      '@layer components {\n  @container (min-width: 600px) {\n    /* fixture for @container stylelint test */\n  }\n}\n',
    );
    try {
      let result: string;
      try {
        result = execSync(
          `./node_modules/.bin/stylelint "${fixturePath}"`,
          { cwd: repoRoot, encoding: 'utf-8' },
        );
      } catch (err: unknown) {
        const error = err as { stdout: string; stderr: string };
        throw new Error(`stylelint failed on @container fixture:\n${error.stdout}\n${error.stderr}`);
      }
      expect(result).toBe('');
    } finally {
      fs.unlinkSync(fixturePath);
    }
  });

  it('theme-blank/package.json exports block resolves @markommerce/theme-blank/css/components/mk-stack.css to the new file (proxy test for the wildcard pattern; if the existing exports block has no wildcard, add "./css/components/*.css": "./resources/css/components/*.css")', () => {
    const pkgPath = path.join(__dirname, '../../../package.json');
    const pkg = JSON.parse(fs.readFileSync(pkgPath, 'utf-8')) as Record<string, unknown>;
    const exports = pkg['exports'] as Record<string, unknown>;
    // Either an exact export for mk-stack.css or a wildcard pattern
    const hasWildcard = Object.keys(exports).some(
      (key) => key.includes('components') && key.includes('*'),
    );
    const hasExact = './css/components/mk-stack.css' in exports;
    expect(hasWildcard || hasExact, 'exports block should expose css/components/ files').toBe(true);
  });
});
