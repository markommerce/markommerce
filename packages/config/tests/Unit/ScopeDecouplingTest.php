<?php

declare(strict_types=1);

it(
    'walks every PHP file under packages/config/src and reports zero occurrences of Markommerce\Scope\ FQN prefix',
    function (): void {
        $srcDir = dirname(__DIR__, 2) . '/src';
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($srcDir));

        $violations = [];

        foreach ($files as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $contents = (string) file_get_contents($file->getPathname());

            if (str_contains($contents, 'Markommerce\\Scope\\')) {
                $violations[] = $file->getPathname();
            }
        }

        expect($violations)->toBeEmpty(
            'Source files with Markommerce\\Scope\\ FQN: ' . implode(', ', $violations),
        );
    },
);

it(
    'walks every PHP file under packages/config/src and reports zero occurrences of ScopeContext, ScopeSignature, ScopeRegistryInterface, ScopedFieldRegistry, SignatureCandidateEnumerator, OverrideMatcher, or AxisNotDeclaredException identifiers',
    function (): void {
        $srcDir = dirname(__DIR__, 2) . '/src';
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($srcDir));

        $forbiddenIdentifiers = [
            'ScopeContext',
            'ScopeSignature',
            'ScopeRegistryInterface',
            'ScopedFieldRegistry',
            'SignatureCandidateEnumerator',
            'OverrideMatcher',
            'AxisNotDeclaredException',
        ];

        $violations = [];

        foreach ($files as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $contents = (string) file_get_contents($file->getPathname());

            foreach ($forbiddenIdentifiers as $identifier) {
                if (str_contains($contents, $identifier)) {
                    $violations[] = $file->getPathname() . ' (contains: ' . $identifier . ')';
                }
            }
        }

        expect($violations)->toBeEmpty(
            'Source files with forbidden scope identifiers: ' . implode(', ', $violations),
        );
    },
);

it(
    'walks every PHP file under packages/config/src and reports zero #[Scoped(] attribute usages',
    function (): void {
        $srcDir = dirname(__DIR__, 2) . '/src';
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($srcDir));

        $violations = [];

        foreach ($files as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $contents = (string) file_get_contents($file->getPathname());

            if (str_contains($contents, '#[Scoped(')) {
                $violations[] = $file->getPathname();
            }
        }

        expect($violations)->toBeEmpty(
            'Source files with #[Scoped(] attribute: ' . implode(', ', $violations),
        );
    },
);
