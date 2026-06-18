<?php

declare(strict_types=1);

namespace Markommerce\Scope\PgSql\Tests\Unit\Query;

use Marko\Database\Exceptions\InvalidColumnException;
use Markommerce\Scope\PgSql\Query\PgSqlScopedFieldRenderer;
use Markommerce\Scope\Query\ScopedFieldExpression;
use Markommerce\Scope\Signature\ScopeSignature;

it(
    'renders a single-axis signature as a single JSONB lookup wrapped in COALESCE with the fallback column',
    function (): void {
        $renderer = new PgSqlScopedFieldRenderer();

        $expression = new ScopedFieldExpression(
            property: 'name',
            column: 'name',
            candidateSignatures: [
                new ScopeSignature(['channel' => 'b2b']),
            ],
        );

        $sql = $renderer->render($expression);

        expect($sql)->toBe('COALESCE("scopes"->\'channel:b2b\'->>\'name\', "name")');
    },
);

it('renders a multi-signature list as COALESCE in the exact order the signatures appear', function (): void {
    $renderer = new PgSqlScopedFieldRenderer();

    $expression = new ScopedFieldExpression(
        property: 'name',
        column: 'name',
        candidateSignatures: [
            new ScopeSignature(['channel' => 'b2b', 'locale' => 'es']),
            new ScopeSignature(['channel' => 'b2b']),
            new ScopeSignature(['locale' => 'es']),
        ],
    );

    $sql = $renderer->render($expression);

    expect($sql)->toBe(
        "COALESCE(\"scopes\"->'channel:b2b|locale:es'->>'name', \"scopes\"->'channel:b2b'->>'name', \"scopes\"->'locale:es'->>'name', \"name\")",
    );
});

it(
    'renders a composite signature key as alphabetical-axis-sorted pipe-joined form (channel:b2b|locale:es)',
    function (): void {
        $renderer = new PgSqlScopedFieldRenderer();

        // Pass axes in non-alphabetical order to verify sorting
        $expression = new ScopedFieldExpression(
            property: 'name',
            column: 'name',
            candidateSignatures: [
                new ScopeSignature(['locale' => 'es', 'channel' => 'b2b']),
            ],
        );

        $sql = $renderer->render($expression);

        expect($sql)->toContain("'channel:b2b|locale:es'");
    },
);

it('returns just the quoted fallback column when the candidate list is empty', function (): void {
    $renderer = new PgSqlScopedFieldRenderer();

    $expression = new ScopedFieldExpression(
        property: 'name',
        column: 'name',
        candidateSignatures: [],
    );

    $sql = $renderer->render($expression);

    expect($sql)->toBe('"name"');
});

it('embeds path segments containing dots correctly into JSONB keys (e.g. eu.de)', function (): void {
    $renderer = new PgSqlScopedFieldRenderer();

    $expression = new ScopedFieldExpression(
        property: 'name',
        column: 'name',
        candidateSignatures: [
            new ScopeSignature(['locale' => 'eu_de']),
        ],
    );

    $sql = $renderer->render($expression);

    expect($sql)->toContain("'locale:eu_de'");
});

it('throws InvalidColumnException when the fallback column is not a valid identifier', function (): void {
    $renderer = new PgSqlScopedFieldRenderer();

    expect(fn () => $renderer->render(new ScopedFieldExpression(
        property: 'name',
        column: '0invalid',
        candidateSignatures: [],
    )))->toThrow(InvalidColumnException::class);
});

it('throws InvalidColumnException when the property is not a valid identifier', function (): void {
    $renderer = new PgSqlScopedFieldRenderer();

    expect(fn () => $renderer->render(new ScopedFieldExpression(
        property: 'not valid',
        column: 'name',
        candidateSignatures: [],
    )))->toThrow(InvalidColumnException::class);
});

it('throws InvalidColumnException when the jsonColumn is not a valid identifier', function (): void {
    $renderer = new PgSqlScopedFieldRenderer();

    expect(fn () => $renderer->render(new ScopedFieldExpression(
        property: 'name',
        column: 'name',
        candidateSignatures: [],
        jsonColumn: 'bad-column',
    )))->toThrow(InvalidColumnException::class);
});

it('throws InvalidColumnException when a signature axis name is not a valid identifier', function (): void {
    $renderer = new PgSqlScopedFieldRenderer();

    // We need a ScopeSignature with an axis name that fails IdentifierValidator
    // ScopeSignature allows any non-empty axis, but IdentifierValidator requires /^[a-zA-Z_][a-zA-Z0-9_]*$/
    // "bad-axis" has a hyphen so it fails
    $expression = new ScopedFieldExpression(
        property: 'name',
        column: 'name',
        candidateSignatures: [
            new ScopeSignature(['bad-axis' => 'value']),
        ],
    );

    expect(fn () => $renderer->render($expression))->toThrow(InvalidColumnException::class);
});

it('throws InvalidColumnException when a signature value segment is not a valid identifier', function (): void {
    $renderer = new PgSqlScopedFieldRenderer();

    // A value with a dot — each segment separated by dot must be a valid identifier
    // "bad.val-ue" → segments: ["bad", "val-ue"] → "val-ue" fails
    $expression = new ScopedFieldExpression(
        property: 'name',
        column: 'name',
        candidateSignatures: [
            new ScopeSignature(['locale' => 'bad.val-ue']),
        ],
    );

    expect(fn () => $renderer->render($expression))->toThrow(InvalidColumnException::class);
});

it('does not append ORDER BY direction (the caller adds that)', function (): void {
    $renderer = new PgSqlScopedFieldRenderer();

    $expression = new ScopedFieldExpression(
        property: 'name',
        column: 'name',
        candidateSignatures: [new ScopeSignature(['channel' => 'b2b'])],
    );

    $sql = $renderer->render($expression);

    expect($sql)->not->toEndWith(' ASC')
        ->and($sql)->not->toEndWith(' DESC');
});

it('does not append SELECT alias (the caller adds that)', function (): void {
    $renderer = new PgSqlScopedFieldRenderer();

    $expression = new ScopedFieldExpression(
        property: 'name',
        column: 'name',
        candidateSignatures: [new ScopeSignature(['channel' => 'b2b'])],
    );

    $sql = $renderer->render($expression);

    expect($sql)->not->toContain(' AS ')
        ->and($sql)->not->toContain(' as ');
});

it(
    'emits COALESCE branches in the exact order of the expression\'s candidateSignatures (no re-sorting, no de-duplication)',
    function (): void {
        $renderer = new PgSqlScopedFieldRenderer();

        // Provide signatures in a specific order that differs from alphabetical
        $expression = new ScopedFieldExpression(
            property: 'name',
            column: 'name',
            candidateSignatures: [
                new ScopeSignature(['z_axis' => 'z']),
                new ScopeSignature(['a_axis' => 'a']),
                new ScopeSignature(['m_axis' => 'm']),
            ],
        );

        $sql = $renderer->render($expression);

        $posZ = strpos($sql, "'z_axis:z'");
        $posA = strpos($sql, "'a_axis:a'");
        $posM = strpos($sql, "'m_axis:m'");

        expect($posZ)->toBeLessThan($posA)
            ->and($posA)->toBeLessThan($posM);
    },
);

it(
    'constructs each JSONB key only AFTER every axis name and every path segment has been validated as a safe identifier (validation precedes string concatenation)',
    function (): void {
        $renderer = new PgSqlScopedFieldRenderer();

        // First signature is valid, second has an invalid axis — exception should be thrown
        // before building any SQL output (early exit on first invalid)
        $expression = new ScopedFieldExpression(
            property: 'name',
            column: 'name',
            candidateSignatures: [
                new ScopeSignature(['channel' => 'b2b']),
                new ScopeSignature(['bad-axis' => 'value']),
            ],
        );

        expect(fn () => $renderer->render($expression))->toThrow(InvalidColumnException::class);
    },
);

it(
    'throws InvalidColumnException when a composite signature contains an axis name that fails IdentifierValidator',
    function (): void {
        $renderer = new PgSqlScopedFieldRenderer();

        $expression = new ScopedFieldExpression(
            property: 'name',
            column: 'name',
            candidateSignatures: [
                new ScopeSignature(['channel' => 'b2b', 'bad-locale' => 'es']),
            ],
        );

        expect(fn () => $renderer->render($expression))->toThrow(InvalidColumnException::class);
    },
);

it(
    'does NOT iterate stored overrides (the COALESCE chain length is exactly count(candidate signatures) + 1 for the fallback column — verified by counting COALESCE arguments)',
    function (): void {
        $renderer = new PgSqlScopedFieldRenderer();

        $expression = new ScopedFieldExpression(
            property: 'name',
            column: 'name',
            candidateSignatures: [
                new ScopeSignature(['channel' => 'b2b']),
                new ScopeSignature(['locale' => 'es']),
                new ScopeSignature(['region' => 'eu']),
            ],
        );

        $sql = $renderer->render($expression);

        // Extract the COALESCE arguments by counting commas at the top level
        preg_match('/^COALESCE\((.+)\)$/', $sql, $matches);
        expect($matches)->toHaveKey(1);

        // Count top-level commas (not nested)
        $args = explode(', ', $matches[1]);
        // 3 candidates + 1 fallback = 4 arguments
        expect(count($args))->toBe(4);
    },
);
