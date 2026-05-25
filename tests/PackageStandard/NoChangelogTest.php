<?php

declare(strict_types=1);

it('asserts no packages/*/CHANGELOG.md file exists', function (): void {
    $packagesDir = __DIR__ . '/../../packages';
    $changelogs = glob($packagesDir . '/*/CHANGELOG.md');

    expect($changelogs)->toBeEmpty('Per-package CHANGELOG.md files are not maintained; use the monorepo CHANGELOG instead.');
});
