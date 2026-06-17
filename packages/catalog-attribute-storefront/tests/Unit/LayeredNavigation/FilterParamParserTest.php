<?php

declare(strict_types=1);

use Marko\Routing\Http\Request;
use Markommerce\Attribute\Entity\AttributeDefinition;
use Markommerce\CatalogAttributeIndex\Tests\Support\QueryableAttributeDefinitionRepository;
use Markommerce\CatalogAttributeStorefront\LayeredNavigation\FilterParamParser;

// ─── Helpers ─────────────────────────────────────────────────────────────────

function makeParserDefRepo(): QueryableAttributeDefinitionRepository
{
    return new QueryableAttributeDefinitionRepository();
}

function makeParserDef(
    QueryableAttributeDefinitionRepository $repo,
    string $code,
): AttributeDefinition {
    $def = new AttributeDefinition();
    $def->code = $code;
    $def->entityType = 'product';
    $def->type = 'select';
    $def->backing = 'Json';
    $def->facetable = true;
    $def->filterable = true;
    $repo->save($def);

    return $def;
}

function makeParserRequest(array $query = []): Request
{
    return new Request(query: $query);
}

// ─── Tests ───────────────────────────────────────────────────────────────────

it('parses the bracketed filter array query param into a multi-value filter selection', function (): void {
    $repo = makeParserDefRepo();
    makeParserDef($repo, 'color');
    makeParserDef($repo, 'size');

    $parser  = new FilterParamParser($repo);
    $request = makeParserRequest(['filter' => ['color' => ['red', 'blue'], 'size' => ['L']]]);

    $selection = $parser->parse($request);

    expect($selection->keys())->toBe(['color', 'size'])
        ->and($selection->forKey('color'))->toBe(['red', 'blue'])
        ->and($selection->forKey('size'))->toBe(['L']);
});

it('normalizes a scalar filter value into a single-element list', function (): void {
    $repo = makeParserDefRepo();
    makeParserDef($repo, 'color');

    $parser  = new FilterParamParser($repo);
    // scalar: filter[color]=red (no brackets)
    $request = makeParserRequest(['filter' => ['color' => 'red']]);

    $selection = $parser->parse($request);

    expect($selection->forKey('color'))->toBe(['red']);
});

it('ignores filter keys that are not known facetable attribute codes', function (): void {
    $repo = makeParserDefRepo();
    makeParserDef($repo, 'color');
    // 'unknown_attr' is NOT in the repository

    $parser  = new FilterParamParser($repo);
    $request = makeParserRequest(['filter' => ['color' => ['red'], 'unknown_attr' => ['val']]]);

    $selection = $parser->parse($request);

    expect($selection->keys())->toBe(['color'])
        ->and($selection->forKey('unknown_attr'))->toBe([]);
});

it('builds a selection from a raw filter array keeping only known facetable codes', function (): void {
    $repo = makeParserDefRepo();
    makeParserDef($repo, 'color');
    makeParserDef($repo, 'size');
    // 'brand' is intentionally NOT registered as a facetable definition.

    $parser = new FilterParamParser($repo);

    $selection = $parser->fromArray([
        'color' => ['red', 'blue'],
        'size'  => ['L'],
        'brand' => ['acme'],
    ]);

    expect($selection->keys())->toBe(['color', 'size'])
        ->and($selection->forKey('color'))->toBe(['red', 'blue'])
        ->and($selection->forKey('size'))->toBe(['L'])
        ->and($selection->forKey('brand'))->toBe([]);
});

it('normalizes a scalar filter value into a single-element list via fromArray', function (): void {
    $repo = makeParserDefRepo();
    makeParserDef($repo, 'color');

    $parser = new FilterParamParser($repo);

    // Scalar (bracket-less) value: filter[color]=red
    $selection = $parser->fromArray(['color' => 'red']);

    expect($selection->forKey('color'))->toBe(['red']);
});
