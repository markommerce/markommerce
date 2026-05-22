<?php

declare(strict_types=1);

namespace Markommerce\Layout\Contracts;

/**
 * A decorator wraps a rendered component's HTML with additional markup.
 *
 * Contract:
 *   - Input:  $innerHtml — the already-rendered HTML of the wrapped component.
 *             $data      — optional associative array of the decorator's own resolved data.
 *   - Output: the fully wrapped HTML string, with {slot inner} replaced by $innerHtml.
 *
 * The decorator's template() MUST contain a `{slot inner}` placeholder.
 * This is validated at compile-time (when a WrapWith operation is resolved)
 * by DecoratorTemplateValidator so that missing placeholders surface during
 * `layout:compile`, not at render time.
 *
 * A decorator MAY add surrounding markup and MAY have its own data() method,
 * but MUST NOT receive or rewrite the wrapped component's props.
 */
interface DecoratorInterface
{
    /**
     * Returns the decorator's template string.
     * MUST contain the literal placeholder `{slot inner}`.
     */
    public function template(): string;

    /**
     * Wraps the inner HTML produced by the decorated component.
     *
     * @param array<string, mixed> $data Optional decorator-own resolved data.
     */
    public function wrap(string $innerHtml, array $data = []): string;
}
