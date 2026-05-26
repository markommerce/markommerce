<?php

declare(strict_types=1);

$apiRefFile = dirname(__DIR__, 4) . '/docs/src/content/docs/packages/layout.md';

it(
    'updates the layout package API reference to include the new contracts, value objects, and exceptions',
    function () use ($apiRefFile): void {
        $content = file_get_contents($apiRefFile);
        expect($content)->not->toBeFalse();
        /** @var string $content */

        // HandleProvider in contracts table
        expect($content)->toContain('HandleProvider');
        expect($content)->toContain('provide(array $props): array');

        // ProvideHandle value object
        expect($content)->toContain('ProvideHandle');
        expect($content)->toContain('$provider');
        expect($content)->toContain('$props');

        // Layout constructor additions
        expect($content)->toContain('$inherits');
        expect($content)->toContain('$handleProviders');
        expect($content)->toContain('$operations');

        // Seven new exceptions
        expect($content)->toContain('CircularInheritanceException');
        expect($content)->toContain('UnknownParentHandleException');
        expect($content)->toContain('DefaultHandleConflictException');
        expect($content)->toContain('DynamicHandleConflictException');
        expect($content)->toContain('UnknownDynamicHandleException');
        expect($content)->toContain('DuplicateContextTokenException');
        expect($content)->toContain('ChainedHandleProviderException');
    },
);
