<?php

declare(strict_types=1);

use Markommerce\Scope\Query\ScopedFieldExpression;
use Markommerce\Scope\Query\ScopedFieldRendererInterface;
use Markommerce\Scope\Signature\ScopeSignature;

it('ScopedFieldRendererInterface declares a single render method returning a string', function (): void {
    $reflection = new ReflectionClass(ScopedFieldRendererInterface::class);
    $methods = $reflection->getMethods();

    expect($methods)->toHaveCount(1);

    $renderMethod = $reflection->getMethod('render');

    expect($renderMethod->getReturnType()?->getName())->toBe('string');

    $params = $renderMethod->getParameters();

    expect($params)->toHaveCount(1)
        ->and($params[0]->getType()?->getName())->toBe(ScopedFieldExpression::class);

    $renderer = new class () implements ScopedFieldRendererInterface
    {
        public function render(ScopedFieldExpression $expression): string
        {
            return 'COALESCE(expr)';
        }
    };

    $expression = new ScopedFieldExpression(
        property: 'price',
        column: 'price',
        candidateSignatures: [new ScopeSignature(['store' => 'en'])],
    );

    expect($renderer->render($expression))->toBeString()->not->toBeEmpty();
});
