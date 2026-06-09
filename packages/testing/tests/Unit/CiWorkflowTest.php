<?php

declare(strict_types=1);

function ciWorkflowPath(): string
{
    return dirname(__DIR__, 4) . '/.github/workflows/ci.yml';
}

function ciWorkflowContent(): string
{
    $path = ciWorkflowPath();
    expect(file_exists($path))->toBeTrue("CI workflow file must exist at {$path}");

    return (string) file_get_contents($path);
}

it('defines a CI integration job with a postgres service and DB env', function (): void {
    $yaml = ciWorkflowContent();

    expect($yaml)->toContain('integration:');
    expect($yaml)->toContain('services:');
    expect($yaml)->toContain('postgres:');
    expect($yaml)->toContain('DB_HOST');
    expect($yaml)->toContain('DB_PORT');
    expect($yaml)->toContain('DB_DATABASE');
    expect($yaml)->toContain('DB_USERNAME');
    expect($yaml)->toContain('DB_PASSWORD');
});

it('grants the CI database user createdb privilege', function (): void {
    $yaml = ciWorkflowContent();

    // The postgres superuser has CREATEDB by default.
    // Verify the workflow uses the postgres superuser.
    expect($yaml)->toContain('POSTGRES_USER: postgres');
});

it('runs the integration-destructive suite in CI via composer test:integration', function (): void {
    $yaml = ciWorkflowContent();

    expect($yaml)->toContain('test:integration');
});

it('runs the unit suite as a separate fast job', function (): void {
    $yaml = ciWorkflowContent();

    expect($yaml)->toContain('unit:');
    expect($yaml)->toContain('composer test');
});

it('runs phpstan with raised memory in CI', function (): void {
    $yaml = ciWorkflowContent();

    expect($yaml)->toContain('static:');
    expect($yaml)->toContain('phpstan');
    expect($yaml)->toContain('memory_limit');
});
