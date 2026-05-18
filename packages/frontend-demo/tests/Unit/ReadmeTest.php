<?php

declare(strict_types=1);

it('the frontend-demo README mentions theme-blank-demo and the /markommerce/_demo/theme-blank route', function (): void {
    $path = __DIR__ . '/../../README.md';
    $contents = file_get_contents($path);

    expect($contents)->toContain('theme-blank-demo');
    expect($contents)->toContain('/markommerce/_demo/theme-blank');
});
