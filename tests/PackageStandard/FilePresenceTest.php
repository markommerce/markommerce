<?php

declare(strict_types=1);

it('asserts every packages/*/ has a composer.json file', function (): void {
    $packagesDir = __DIR__ . '/../../packages';
    $packages = glob($packagesDir . '/*', GLOB_ONLYDIR) ?: [];

    expect($packages)->not->toBeEmpty();

    foreach ($packages as $packageDir) {
        expect($packageDir . '/composer.json')
            ->toBeFile("Package " . basename($packageDir) . " is missing composer.json");
    }
});

it('asserts every packages/*/ has a README.md file', function (): void {
    $packagesDir = __DIR__ . '/../../packages';
    $packages = glob($packagesDir . '/*', GLOB_ONLYDIR) ?: [];

    expect($packages)->not->toBeEmpty();

    foreach ($packages as $packageDir) {
        expect($packageDir . '/README.md')
            ->toBeFile("Package " . basename($packageDir) . " is missing README.md");
    }
});

it('asserts every packages/*/ has a LICENSE file', function (): void {
    $packagesDir = __DIR__ . '/../../packages';
    $packages = glob($packagesDir . '/*', GLOB_ONLYDIR) ?: [];

    expect($packages)->not->toBeEmpty();

    foreach ($packages as $packageDir) {
        expect($packageDir . '/LICENSE')
            ->toBeFile("Package " . basename($packageDir) . " is missing LICENSE");
    }
});

it('asserts every packages/*/ has a .gitattributes file', function (): void {
    $packagesDir = __DIR__ . '/../../packages';
    $packages = glob($packagesDir . '/*', GLOB_ONLYDIR) ?: [];

    expect($packages)->not->toBeEmpty();

    foreach ($packages as $packageDir) {
        expect($packageDir . '/.gitattributes')
            ->toBeFile("Package " . basename($packageDir) . " is missing .gitattributes");
    }
});

it('asserts every packages/*/ .gitattributes content matches the canonical content from package-standard.md', function (): void {
    $canonicalGitattributes = <<<'GITATTRIBUTES'
/tests              export-ignore
/.github             export-ignore
/.gitattributes     export-ignore
/.gitignore         export-ignore
/phpunit.xml.dist   export-ignore
GITATTRIBUTES;

    $packagesDir = __DIR__ . '/../../packages';
    $packages = glob($packagesDir . '/*', GLOB_ONLYDIR) ?: [];

    expect($packages)->not->toBeEmpty();

    foreach ($packages as $packageDir) {
        $gitattributesFile = $packageDir . '/.gitattributes';
        if (!is_file($gitattributesFile)) {
            continue;
        }

        $rawContent = file_get_contents($gitattributesFile);
        assert(is_string($rawContent));
        $content = rtrim($rawContent);
        expect($content)
            ->toBe($canonicalGitattributes, "Package " . basename($packageDir) . " .gitattributes does not match canonical content");
    }
});

it('asserts every packages/*/ LICENSE content matches the canonical MIT text from package-standard.md', function (): void {
    $canonicalLicense = <<<'LICENSE'
MIT License

Copyright (c) Devtomic LLC

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
LICENSE;

    $packagesDir = __DIR__ . '/../../packages';
    $packages = glob($packagesDir . '/*', GLOB_ONLYDIR) ?: [];

    expect($packages)->not->toBeEmpty();

    foreach ($packages as $packageDir) {
        $licenseFile = $packageDir . '/LICENSE';
        if (!is_file($licenseFile)) {
            continue;
        }

        $rawContent = file_get_contents($licenseFile);
        assert(is_string($rawContent));
        $content = rtrim($rawContent);
        expect($content)
            ->toBe($canonicalLicense, "Package " . basename($packageDir) . " LICENSE does not match canonical MIT text");
    }
});
