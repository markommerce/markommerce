import { describe, it, expect, afterEach } from 'vitest';
import * as fs from 'node:fs';
import * as os from 'node:os';
import * as path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
// __dirname = <root>/packages/frontend-demo/resources/js
//          or <root>/vendor/markommerce/frontend-demo/resources/js
const demoPackageRoot = path.resolve(__dirname, '../..');
// demoPackageRoot = <root>/packages/frontend-demo or <root>/vendor/markommerce/frontend-demo

// Find repo root by searching upward for package-lock.json (present only at repo root).
function findRepoRoot(startDir: string): string {
  let dir = startDir;
  while (dir !== path.dirname(dir)) {
    if (fs.existsSync(path.join(dir, 'package-lock.json'))) {
      return dir;
    }
    dir = path.dirname(dir);
  }
  throw new Error(`Could not find repo root from ${startDir}`);
}

const packageRoot = findRepoRoot(__dirname);
const canonicalDemoRoot = path.join(packageRoot, 'packages/frontend-demo');
const packageJsonPath = path.join(demoPackageRoot, 'package.json');

function readPackageJson(): Record<string, unknown> {
  const content = fs.readFileSync(packageJsonPath, 'utf-8');
  return JSON.parse(content) as Record<string, unknown>;
}

describe('@markommerce/frontend-demo npm package', () => {
  it('has a package.json declaring name @markommerce/frontend-demo and type module', () => {
    expect(fs.existsSync(packageJsonPath)).toBe(true);
    const pkg = readPackageJson();
    expect(pkg['name']).toBe('@markommerce/frontend-demo');
    expect(pkg['type']).toBe('module');
  });

  it('sets markommerce.extension to ./resources/js/index.ts', () => {
    const pkg = readPackageJson();
    const markommerce = pkg['markommerce'] as Record<string, unknown>;
    expect(markommerce).toBeDefined();
    expect(markommerce['extension']).toBe('./resources/js/index.ts');
  });

  it('sets markommerce.priority to 1000', () => {
    const pkg = readPackageJson();
    const markommerce = pkg['markommerce'] as Record<string, unknown>;
    expect(markommerce).toBeDefined();
    expect(markommerce['priority']).toBe(1000);
  });

  it('declares @markommerce/frontend as a workspace dependency', () => {
    const pkg = readPackageJson();
    const deps = pkg['dependencies'] as Record<string, string> | undefined;
    expect(deps).toBeDefined();
    expect(deps?.['@markommerce/frontend']).toBe('*');
  });

  it('declares lit as a peer dependency', () => {
    const pkg = readPackageJson();
    const peerDeps = pkg['peerDependencies'] as Record<string, string> | undefined;
    expect(peerDeps).toBeDefined();
    expect(peerDeps?.['lit']).toBeDefined();
  });

  it("declares open-props as a peer dependency so npm install resolves it via the kernel's declared range", () => {
    const pkg = readPackageJson();
    const peerDeps = pkg['peerDependencies'] as Record<string, string> | undefined;
    expect(peerDeps).toBeDefined();
    expect(peerDeps?.['open-props']).toBeDefined();
  });

  it('creates resources/js/main.ts that imports cascade layers, Open Props, tokens, the generated extensions file, demo component CSS, and calls defineAllComponents in that order', () => {
    // Always read from the canonical source package, not the vendor copy.
    const mainTsPath = path.join(canonicalDemoRoot, 'resources/js/main.ts');
    expect(fs.existsSync(mainTsPath)).toBe(true);
    const content = fs.readFileSync(mainTsPath, 'utf-8');

    // Check all required imports are present
    expect(content).toContain("import '@markommerce/frontend/css/layers.css'");
    expect(content).toContain("import 'open-props/style.css'");
    expect(content).toContain("import '@markommerce/theme-blank/css/tokens.css'");
    expect(content).toContain("import '@markommerce/theme-blank/css/base.css'");
    expect(content).toContain("import './.generated/extensions'");
    expect(content).toContain("import '../css/components/counter.css'");
    expect(content).toContain("import { defineAllComponents } from '@markommerce/frontend'");
    expect(content).toContain('defineAllComponents()');

    // Check import order: layers.css → open-props → tokens.css → base.css → extensions → counter.css → defineAllComponents
    const layersPos = content.indexOf("import '@markommerce/frontend/css/layers.css'");
    const openPropsPos = content.indexOf("import 'open-props/style.css'");
    const tokensPos = content.indexOf("import '@markommerce/theme-blank/css/tokens.css'");
    const baseCssPos = content.indexOf("import '@markommerce/theme-blank/css/base.css'");
    const extensionsPos = content.indexOf("import './.generated/extensions'");
    const counterCssPos = content.indexOf("import '../css/components/counter.css'");
    const definePos = content.indexOf("import { defineAllComponents } from '@markommerce/frontend'");

    expect(layersPos).toBeLessThan(openPropsPos);
    expect(openPropsPos).toBeLessThan(tokensPos);
    expect(tokensPos).toBeLessThan(baseCssPos);
    expect(baseCssPos).toBeLessThan(extensionsPos);
    expect(extensionsPos).toBeLessThan(counterCssPos);
    expect(counterCssPos).toBeLessThan(definePos);
  });

  it('creates a stub resources/js/index.ts importable as a side effect', () => {
    const indexTsPath = path.join(canonicalDemoRoot, 'resources/js/index.ts');
    expect(fs.existsSync(indexTsPath)).toBe(true);
    const content = fs.readFileSync(indexTsPath, 'utf-8');
    // Must be a valid ES module (either has export or is empty with valid syntax)
    expect(content).toContain('export');
  });

  it('pre-seeds resources/js/.generated/extensions.ts with an "export {}" placeholder so tsc --noEmit resolves the main.ts import before the first Vite run', () => {
    // The placeholder file must exist at this path. Its content is `export {}` when committed,
    // but may be overwritten by the Vite plugin after a build.
    // We verify the file exists and is a valid ES module (either the placeholder or generated content).
    const extensionsPath = path.join(canonicalDemoRoot, 'resources/js/.generated/extensions.ts');
    expect(fs.existsSync(extensionsPath)).toBe(true);
    const content = fs.readFileSync(extensionsPath, 'utf-8');
    // The file must be a valid ES module — either the placeholder `export {}` or the generated
    // content which also exports (LOADED_MODULES). In both cases it contains `export`.
    expect(content).toMatch(/export/);
  });

  it('gitignores generated content in resources/js/.generated/ while keeping the placeholder under source control', () => {
    const generatedDir = path.join(canonicalDemoRoot, 'resources/js/.generated');
    expect(fs.existsSync(generatedDir)).toBe(true);

    // Check that either a local .gitignore exists in .generated/ or the root .gitignore covers it
    const localGitignorePath = path.join(generatedDir, '.gitignore');
    const rootGitignorePath = path.join(packageRoot, '.gitignore');

    const localExists = fs.existsSync(localGitignorePath);
    const rootContent = fs.readFileSync(rootGitignorePath, 'utf-8');
    const rootCoversGenerated =
      rootContent.includes('packages/*/resources/js/.generated/') ||
      rootContent.includes('.generated/');

    expect(localExists || rootCoversGenerated).toBe(true);

    // The placeholder must actually exist (committed)
    const extensionsPlaceholder = path.join(generatedDir, 'extensions.ts');
    expect(fs.existsSync(extensionsPlaceholder)).toBe(true);
  });

  it('npm install at the repo root resolves @markommerce/frontend-demo via the workspace', () => {
    // Verified by checking the package is registered as a workspace package.
    // npm workspace resolution is confirmed by the root package.json having workspaces: ["packages/*"]
    const pkg = readPackageJson();
    expect(pkg['name']).toBe('@markommerce/frontend-demo');
    const rootPkgPath = path.join(packageRoot, 'package.json');
    const rootPkg = JSON.parse(fs.readFileSync(rootPkgPath, 'utf-8')) as Record<string, unknown>;
    const workspaces = rootPkg['workspaces'] as string[] | undefined;
    expect(workspaces).toContain('packages/*');
  });

  it('the frontend-demo main.ts imports @markommerce/theme-blank/css/layouts.css after base.css', () => {
    const mainTsPath = path.join(canonicalDemoRoot, 'resources/js/main.ts');
    expect(fs.existsSync(mainTsPath)).toBe(true);
    const content = fs.readFileSync(mainTsPath, 'utf-8');

    expect(content).toContain("import '@markommerce/theme-blank/css/layouts.css'");

    const baseCssPos = content.indexOf("import '@markommerce/theme-blank/css/base.css'");
    const layoutsCssPos = content.indexOf("import '@markommerce/theme-blank/css/layouts.css'");

    expect(baseCssPos).toBeGreaterThanOrEqual(0);
    expect(layoutsCssPos).toBeGreaterThanOrEqual(0);
    expect(baseCssPos).toBeLessThan(layoutsCssPos);
  });

  describe('the Vite scanner plugin run end-to-end with both packages produces a generated extensions file listing the kernel first and the demo second', () => {
    let tempOutputDir: string;

    afterEach(() => {
      if (tempOutputDir) {
        fs.rmSync(tempOutputDir, { recursive: true, force: true });
      }
    });

    it('the Vite scanner plugin run end-to-end with both packages produces a generated extensions file listing the kernel first and the demo second', async () => {
      // Import the plugin from the build directory
      const { default: markommerceModuleScanner } = await import(
        path.join(packageRoot, 'build/vite-plugin-markommerce.ts')
      );

      // Use a temp output path to avoid mutating the committed placeholder
      tempOutputDir = fs.mkdtempSync(path.join(os.tmpdir(), 'markommerce-e2e-'));
      const tempOutputPath = path.join(tempOutputDir, 'extensions.ts');

      const plugin = markommerceModuleScanner({
        packagesPath: path.join(packageRoot, 'packages'),
        outputPath: tempOutputPath,
      });

      // Invoke buildStart hook
      if (typeof plugin.buildStart === 'function') {
        await (plugin.buildStart as () => Promise<void>)();
      }

      expect(fs.existsSync(tempOutputPath)).toBe(true);

      const content = fs.readFileSync(tempOutputPath, 'utf-8');
      const frontendPos = content.indexOf('@markommerce/frontend');
      const demoPos = content.indexOf('@markommerce/frontend-demo');
      expect(frontendPos).toBeGreaterThanOrEqual(0);
      expect(demoPos).toBeGreaterThanOrEqual(0);
      expect(frontendPos).toBeLessThan(demoPos);
    });
  });
});
