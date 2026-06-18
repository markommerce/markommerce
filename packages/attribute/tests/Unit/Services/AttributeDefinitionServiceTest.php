<?php

declare(strict_types=1);

use Marko\Database\Entity\EntityMetadataFactory;
use Markommerce\Attribute\Contracts\AttributeDefinitionInterface;
use Markommerce\Attribute\Contracts\AttributeDefinitionRepositoryInterface;
use Markommerce\Attribute\Contracts\AttributeTypeInterface;
use Markommerce\Attribute\Entity\AttributeDefinition;
use Markommerce\Attribute\Entity\AttributeOption;
use Markommerce\Attribute\Exceptions\DuplicateAttributeCodeException;
use Markommerce\Attribute\Exceptions\ReservedAttributeCodeException;
use Markommerce\Attribute\Exceptions\UnknownAttributeTypeException;
use Markommerce\Attribute\Registry\AttributeTypeRegistry;
use Markommerce\Attribute\Reserved\ReservedCodeProvider;
use Markommerce\Attribute\Services\AttributeDefinitionService;
use Markommerce\Attribute\Tests\Fixtures\SimpleEntity;
use Markommerce\Attribute\Tests\Support\FakeAttributeDefinitionRepository;
use Markommerce\Attribute\Type\FacetKind;

function makeAttributeDefinitionTypeRegistry(string ...$codes): AttributeTypeRegistry
{
    $registry = new AttributeTypeRegistry();

    foreach ($codes as $code) {
        $registry->register(new class ($code) implements AttributeTypeInterface
        {
            public function __construct(private readonly string $typeCode) {}

            public function code(): string
            {
                return $this->typeCode;
            }

            public function cast(
                mixed $raw,
                AttributeDefinitionInterface $definition,
            ): mixed {
                return $raw;
            }

            public function serialize(mixed $value): mixed
            {
                return $value;
            }

            public function deserialize(mixed $stored): mixed
            {
                return $stored;
            }

            public function facetKind(): FacetKind
            {
                return FacetKind::None;
            }
        });
    }

    return $registry;
}

function makeAttributeDefinitionService(
    ?FakeAttributeDefinitionRepository $repo = null,
    ?AttributeTypeRegistry $registry = null,
    array $entityTypeMap = [],
): AttributeDefinitionService {
    return new AttributeDefinitionService(
        attributeDefinitionRepository: $repo ?? new FakeAttributeDefinitionRepository(),
        attributeTypeRegistry: $registry ?? makeAttributeDefinitionTypeRegistry('text', 'select', 'multiselect'),
        reservedCodeProvider: new ReservedCodeProvider(new EntityMetadataFactory()),
        entityTypeMap: $entityTypeMap,
    );
}

function makeNewAttributeDefinition(
    string $code = 'my_attr',
    string $entityType = 'product',
    string $type = 'text',
): AttributeDefinition {
    $definition = new AttributeDefinition();
    $definition->code = $code;
    $definition->entityType = $entityType;
    $definition->type = $type;
    $definition->label = ucfirst($code);

    return $definition;
}

it('creates an attribute definition with a valid code and registered type', function (): void {
    $repo = new FakeAttributeDefinitionRepository();
    $service = makeAttributeDefinitionService(repo: $repo);

    $definition = makeNewAttributeDefinition('brand', 'product', 'text');
    $service->create($definition);

    expect($definition->id)->not->toBeNull();

    $found = $repo->findByCode('product', 'brand');
    expect($found)->not->toBeNull()
        ->and($found->code)->toBe('brand');
});

it('rejects a definition whose type is not registered', function (): void {
    $service = makeAttributeDefinitionService();

    $definition = makeNewAttributeDefinition('brand', 'product', 'unknown_type');

    expect(fn () => $service->create($definition))->toThrow(UnknownAttributeTypeException::class);
});

it('rejects a definition whose code collides with a reserved entity column', function (): void {
    $entityTypeMap = ['product' => SimpleEntity::class];
    $service = makeAttributeDefinitionService(entityTypeMap: $entityTypeMap);

    // 'name' is a column on SimpleEntity and therefore reserved
    $definition = makeNewAttributeDefinition('name', 'product', 'text');

    expect(fn () => $service->create($definition))->toThrow(ReservedAttributeCodeException::class);
});

it('rejects a duplicate code within the same entity type', function (): void {
    $repo = new FakeAttributeDefinitionRepository();
    $service = makeAttributeDefinitionService(repo: $repo);

    $first = makeNewAttributeDefinition('color', 'product', 'text');
    $service->create($first);

    $second = makeNewAttributeDefinition('color', 'product', 'text');

    expect(fn () => $service->create($second))->toThrow(DuplicateAttributeCodeException::class);
});

it('allows the same code under a different entity type', function (): void {
    $repo = new FakeAttributeDefinitionRepository();
    $service = makeAttributeDefinitionService(repo: $repo);

    $productAttr = makeNewAttributeDefinition('color', 'product', 'text');
    $service->create($productAttr);

    $categoryAttr = makeNewAttributeDefinition('color', 'category', 'text');
    $service->create($categoryAttr);

    expect($repo->findByCode('product', 'color'))->not->toBeNull()
        ->and($repo->findByCode('category', 'color'))->not->toBeNull();
});

it('attaches select options to a select definition', function (): void {
    $repo = new FakeAttributeDefinitionRepository();
    $service = makeAttributeDefinitionService(repo: $repo);

    $definition = makeNewAttributeDefinition('color', 'product', 'select');

    $red = new AttributeOption();
    $red->value = 'red';
    $red->label = 'Red';
    $red->position = 0;

    $blue = new AttributeOption();
    $blue->value = 'blue';
    $blue->label = 'Blue';
    $blue->position = 1;

    $service->create($definition, [$red, $blue]);

    $options = $repo->optionsFor($definition);
    expect($options)->toHaveCount(2);
});

it('deletes a definition and its options', function (): void {
    $repo = new FakeAttributeDefinitionRepository();
    $service = makeAttributeDefinitionService(repo: $repo);

    $definition = makeNewAttributeDefinition('color', 'product', 'select');

    $option = new AttributeOption();
    $option->value = 'red';
    $option->label = 'Red';

    $service->create($definition, [$option]);

    $defId = $definition->id;
    $service->delete($definition);

    $ghost = new AttributeDefinition();
    $ghost->id = $defId;

    expect($repo->find($defId))->toBeNull()
        ->and($repo->optionsFor($ghost))->toHaveCount(0);
});

it('satisfies the attribute definition repository contract with the in-memory fake', function (): void {
    // The full contract suite is invoked in AttributeDefinitionRepositoryContractInvokeTest.php.
    // This test confirms that FakeAttributeDefinitionRepository implements the interface.
    expect(FakeAttributeDefinitionRepository::class)
        ->toImplement(AttributeDefinitionRepositoryInterface::class);
});
