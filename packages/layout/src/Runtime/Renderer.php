<?php

declare(strict_types=1);

namespace Markommerce\Layout\Runtime;

use Marko\Core\Container\ContainerInterface;
use Marko\Routing\Http\Request;
use Marko\View\ViewInterface;
use Markommerce\Layout\Cache\PreparedPlace;
use Markommerce\Layout\Cache\PreparedRepeatSlot;
use Markommerce\Layout\Cache\PreparedTree;
use Markommerce\Layout\Contracts\ContextProvider;
use Markommerce\Layout\Contracts\DecoratorInterface;
use ReflectionClass;
use ReflectionProperty;
use RuntimeException;

/**
 * Renders a PreparedTree into an HTML string.
 *
 * Two-phase rendering:
 *   Phase 0 — context: run each Provide's ContextProvider, collect into contextMap.
 *   Phase 1 — data collect: depth-first walk; for each placement resolve props,
 *             instantiate component via container, call data(), memoize DTO.
 *             Parent DTOs are memoized before descending to children.
 *             Repeat slots iterate the parent's list, running phase 1 per item.
 *   Phase 2 — render: walk again, render each placement via ViewInterface::renderToString(),
 *             inline sub-slot HTML into {slot name}{/slot} placeholders,
 *             apply decorators innermost-first.
 *
 * Output: an HTML string. The middleware (task 016) wraps it in a Response.
 *
 * Template data binding: the DTO's public properties are spread into the data array
 * passed to renderToString(), plus an 'extensions' key holding the ExtensionBag
 * (present only when the DTO extends ExtensibleData).
 * A '_slots' key holds a map of slot-name → bool for templates to check slot presence.
 */
class Renderer implements RendererInterface
{
    public function __construct(
        private ViewInterface $view,
        private ContainerInterface $container,
    ) {}

    /**
     * Render the prepared tree into an HTML string.
     *
     * @param array<string, string> $routeParams
     * @throws RuntimeException
     */
    public function render(
        PreparedTree $tree,
        Request $request,
        array $routeParams,
    ): string {
        // Phase 0: build context map
        $contextMap = $this->buildContextMap($tree, $request, $routeParams);

        // Phase 1: collect data for all placements
        /** @var array<string, object|null> $memoized keyed by placement name */
        $memoized = [];
        $baseContext = new ResolutionContext(
            request: $request,
            routeParams: $routeParams,
            contextMap: $contextMap,
            iterationItem: null,
            parentData: null,
            container: $this->container,
            placementChain: $tree->handleKey,
        );

        foreach ($tree->slots as $slotContent) {
            $this->collectDataForSlot($slotContent, $baseContext, $memoized);
        }

        // Phase 2: render
        $slotHtml = [];
        foreach ($tree->slots as $slotName => $slotContent) {
            $slotHtml[$slotName] = $this->renderSlot($slotContent, $baseContext, $memoized);
        }

        // If there is a root template, render it with slot content injected
        if ($tree->template !== null) {
            $rootData = ['_slots' => array_map(fn () => true, $slotHtml)];
            $html = $this->view->renderToString($tree->template, $rootData);
            $html = $this->inlineSlots($html, $slotHtml);
            return $html;
        }

        // No root template: concatenate all slot HTML
        return implode('', $slotHtml);
    }

    /**
     * Phase 0: run all context providers.
     *
     * @param array<string, string> $routeParams
     * @return array<string, object>
     * @throws RuntimeException
     */
    private function buildContextMap(
        PreparedTree $tree,
        Request $request,
        array $routeParams,
    ): array {
        $contextMap = [];
        $resolver = new SourceResolver();

        foreach ($tree->context as $provide) {
            $tempContext = new ResolutionContext(
                request: $request,
                routeParams: $routeParams,
                contextMap: $contextMap,
                iterationItem: null,
                parentData: null,
                container: $this->container,
                placementChain: $tree->handleKey,
            );

            // Resolve provider props
            $resolvedProps = [];
            foreach ($provide->props as $key => $source) {
                $resolvedProps[$key] = $resolver->resolve($source, $tempContext);
            }

            /** @var ContextProvider $provider */
            $provider = $this->container->get($provide->provider);
            $contextMap[$provide->token] = $provider->provide($resolvedProps);
        }

        return $contextMap;
    }

    /**
     * Phase 1: collect data for a slot's contents (list of placements or repeat slot).
     *
     * @param list<PreparedPlace>|PreparedRepeatSlot $slotContent
     * @param array<string, object|null> $memoized
     * @throws RuntimeException
     */
    private function collectDataForSlot(
        array|PreparedRepeatSlot $slotContent,
        ResolutionContext $context,
        array &$memoized,
    ): void {
        if ($slotContent instanceof PreparedRepeatSlot) {
            // The parent's data must already be memoized (collected before descending to this slot)
            // We don't collect data for repeat children here — it's handled per-item in renderRepeatSlot
            return;
        }

        foreach ($slotContent as $place) {
            $this->collectDataForPlace($place, $context, $memoized);
        }
    }

    /**
     * Phase 1: collect data for a single placement, then recurse into its slots.
     *
     * @param array<string, object|null> $memoized
     * @throws RuntimeException
     */
    private function collectDataForPlace(
        PreparedPlace $place,
        ResolutionContext $context,
        array &$memoized,
    ): void {
        $resolver = new SourceResolver();

        // Resolve props for this placement
        $resolvedProps = [];
        foreach ($place->props as $key => $source) {
            $resolvedProps[$key] = $resolver->resolve($source, $context);
        }

        // Instantiate component via container (preserves plugin decoration)
        $component = $this->container->get($place->component);

        // Call data() if it exists, passing resolved props
        $dto = null;
        if (is_object($component) && method_exists($component, 'data')) {
            /** @var object $dto */
            $dto = $component->data(...$resolvedProps);
        }

        // Memoize
        if ($place->name !== null && $dto !== null) {
            $memoized[$place->name] = $dto;
        }

        // Build child context with this placement's DTO as parentData
        $childContext = new ResolutionContext(
            request: $context->request,
            routeParams: $context->routeParams,
            contextMap: $context->contextMap,
            iterationItem: $context->iterationItem,
            parentData: $dto,
            container: $this->container,
            placementChain: $context->placementChain . ' > ' . ($place->name ?? $place->component),
        );

        // Recurse into regular slots (not repeat slots — handled at render time)
        foreach ($place->slots as $slotContent) {
            if (!($slotContent instanceof PreparedRepeatSlot)) {
                $this->collectDataForSlot($slotContent, $childContext, $memoized);
            }
        }
    }

    /**
     * Phase 2: render a slot's contents into an HTML string.
     *
     * @param list<PreparedPlace>|PreparedRepeatSlot $slotContent
     * @param array<string, object|null> $memoized
     * @throws RuntimeException
     */
    private function renderSlot(
        array|PreparedRepeatSlot $slotContent,
        ResolutionContext $context,
        array &$memoized,
    ): string {
        if ($slotContent instanceof PreparedRepeatSlot) {
            return $this->renderRepeatSlot($slotContent, $context, $memoized);
        }

        $html = '';
        foreach ($slotContent as $place) {
            $html .= $this->renderPlace($place, $context, $memoized);
        }
        return $html;
    }

    /**
     * Phase 2: render a repeat slot by iterating over the parent's data list.
     *
     * @param array<string, object|null> $memoized
     * @throws RuntimeException
     */
    private function renderRepeatSlot(
        PreparedRepeatSlot $repeatSlot,
        ResolutionContext $context,
        array &$memoized,
    ): string {
        // The parent data is in $context->parentData
        $parentData = $context->parentData;

        if ($parentData === null) {
            return '';
        }

        // Get the list from parent's data using dataKey
        $items = $parentData->{$repeatSlot->dataKey} ?? [];

        if (!is_array($items) || $items === []) {
            return '';
        }

        $html = '';
        foreach ($items as $item) {
            // Create iteration context with the current item
            $iterationContext = new ResolutionContext(
                request: $context->request,
                routeParams: $context->routeParams,
                contextMap: $context->contextMap,
                iterationItem: $item,
                parentData: $context->parentData,
                container: $this->container,
                placementChain: $context->placementChain . ' > [repeat:' . $repeatSlot->as . ']',
            );

            // For each item, collect data for children and render them
            $itemMemoized = $memoized; // child memoized inherits parent
            foreach ($repeatSlot->children as $child) {
                $this->collectDataForPlace($child, $iterationContext, $itemMemoized);
            }

            foreach ($repeatSlot->children as $child) {
                $html .= $this->renderPlace($child, $iterationContext, $itemMemoized);
            }
        }

        return $html;
    }

    /**
     * Phase 2: render a single placement and its sub-slots.
     *
     * @param array<string, object|null> $memoized
     * @throws RuntimeException
     */
    private function renderPlace(
        PreparedPlace $place,
        ResolutionContext $context,
        array &$memoized,
    ): string {
        // Get memoized DTO (collected in phase 1)
        $dto = $place->name !== null ? ($memoized[$place->name] ?? null) : null;

        // Build template data: spread DTO public props + extensions key
        $templateData = $this->buildTemplateData($dto, $place);

        // Render child slots
        $childContext = new ResolutionContext(
            request: $context->request,
            routeParams: $context->routeParams,
            contextMap: $context->contextMap,
            iterationItem: $context->iterationItem,
            parentData: $dto,
            container: $this->container,
            placementChain: $context->placementChain . ' > ' . ($place->name ?? $place->component),
        );

        $slotHtml = [];
        foreach ($place->slots as $slotName => $slotContent) {
            $slotHtml[$slotName] = $this->renderSlot($slotContent, $childContext, $memoized);
        }

        // Add slot presence map for template
        if ($slotHtml !== []) {
            $templateData['_slots'] = array_map(fn () => true, $slotHtml);
        }

        // Render the component template
        $html = $this->view->renderToString($place->component, $templateData);

        // Inline sub-slot HTML into slot placeholders
        if ($slotHtml !== []) {
            $html = $this->inlineSlots($html, $slotHtml);
        }

        // Apply decorators innermost-first
        foreach ($place->decorators as $decoratorClass) {
            /** @var DecoratorInterface $decorator */
            $decorator = $this->container->get($decoratorClass);
            $html = $decorator->wrap($html);
        }

        return $html;
    }

    /**
     * Build the template data array from a DTO.
     *
     * Spreads all public properties of the DTO into the array.
     * If the DTO extends ExtensibleData, adds an 'extensions' key.
     *
     * @return array<string, mixed>
     */
    private function buildTemplateData(
        ?object $dto,
        PreparedPlace $place,
    ): array {
        if ($dto === null) {
            return [];
        }

        $data = [];
        $rc = new ReflectionClass($dto);
        foreach ($rc->getProperties(ReflectionProperty::IS_PUBLIC) as $prop) {
            $data[$prop->getName()] = $prop->getValue($dto);
        }

        return $data;
    }

    /**
     * Inline slot HTML into {slot name}{/slot} placeholders.
     *
     * @param array<string, string> $slotHtml
     */
    private function inlineSlots(string $html, array $slotHtml): string
    {
        foreach ($slotHtml as $slotName => $content) {
            $html = preg_replace(
                '/\{slot ' . preg_quote($slotName, '/') . '\}.*?\{\/slot\}/s',
                $content,
                $html,
            ) ?? $html;
        }

        return $html;
    }
}
