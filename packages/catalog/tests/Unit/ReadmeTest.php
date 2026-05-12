<?php

declare(strict_types=1);

it('provides a catalog README with installation usage and the service-over-repository convention', function (): void {
    $readme = file_get_contents(__DIR__ . '/../../README.md');
    expect($readme)->not->toBeFalsy();
    expect($readme)->toContain('ProductServiceInterface');
    expect($readme)->toContain('CategoryServiceInterface');
    expect($readme)->toContain('CategoryAssignmentServiceInterface');
    expect($readme)->toContain('ProductPriceServiceInterface');
    expect($readme)->toContain('service');
    expect($readme)->toContain('repository');
});

it('documents the entity-driven schema approach in the catalog README', function (): void {
    $readme = file_get_contents(__DIR__ . '/../../README.md');
    expect($readme)->toContain('db:migrate');
    expect($readme)->toContain('no SQL files');
});

it('documents the ProductPriceService boundary and the multi-store refactor markers', function (): void {
    $readme = file_get_contents(__DIR__ . '/../../README.md');
    expect($readme)->toContain('basePriceAmount');
    expect($readme)->toContain('multi-store');
    expect($readme)->toContain('@todo multi-store');
});
