<?php

declare(strict_types=1);

use Markommerce\Layout\Attributes\IteratesOver;
use Markommerce\Layout\Contracts\DecoratorInterface;

it('defines an IteratesOver attribute targeting classes', function (): void {
    $reflection = new ReflectionClass(IteratesOver::class);
    $attrs = $reflection->getAttributes(Attribute::class);
    expect($attrs)->not->toBeEmpty();

    /** @var Attribute $attrInstance */
    $attrInstance = $attrs[0]->newInstance();
    expect($attrInstance->flags)->toBe(Attribute::TARGET_CLASS);
});

it('exposes the item type from an IteratesOver attribute', function (): void {
    $attr = new IteratesOver(itemType: \stdClass::class);
    expect($attr->itemType)->toBe(\stdClass::class);
});

it('reads the IteratesOver attribute from an annotated token class via reflection', function (): void {
    $reflection = new ReflectionClass(ProductIterationFixture::class);
    $attrs = $reflection->getAttributes(IteratesOver::class);
    expect($attrs)->not->toBeEmpty();

    /** @var IteratesOver $instance */
    $instance = $attrs[0]->newInstance();
    expect($instance->itemType)->toBe(ProductFixture::class);
});

it('defines a DecoratorInterface contract', function (): void {
    $reflection = new ReflectionClass(DecoratorInterface::class);
    expect($reflection->isInterface())->toBeTrue();

    $methods = array_map(fn (ReflectionMethod $m) => $m->getName(), $reflection->getMethods(ReflectionMethod::IS_PUBLIC));
    expect($methods)->toContain('template')
        ->and($methods)->toContain('wrap');

    $templateMethod = $reflection->getMethod('template');
    expect($templateMethod->getReturnType()?->getName())->toBe('string');

    $wrapMethod = $reflection->getMethod('wrap');
    expect($wrapMethod->getReturnType()?->getName())->toBe('string');
    expect($wrapMethod->getNumberOfParameters())->toBeGreaterThanOrEqual(1);
    expect($wrapMethod->getParameters()[0]->getName())->toBe('innerHtml');
});

it('requires a decorator template to contain a slot inner placeholder', function (): void {
    $validator = new \Markommerce\Layout\Compiler\DecoratorTemplateValidator();

    expect(fn () => $validator->validate(MissingSlotDecoratorFixture::class))
        ->toThrow(\Markommerce\Layout\Exception\MissingSlotInnerException::class);
});

// ---------------------------------------------------------------------------
// Fixtures
// ---------------------------------------------------------------------------

class ProductFixture {}

#[IteratesOver(itemType: ProductFixture::class)]
class ProductIterationFixture {}

class MissingSlotDecoratorFixture implements DecoratorInterface
{
    public function template(): string
    {
        return '<div>No placeholder here</div>';
    }

    public function wrap(string $innerHtml, array $data = []): string
    {
        return str_replace('{slot inner}', $innerHtml, $this->template());
    }
}
