<?php

declare(strict_types=1);

namespace Markommerce\Layout\Contracts;

/**
 * Marker interface for typed extension attributes.
 *
 * Third-party modules implement this interface to attach domain-specific data
 * to a component's data DTO without subclassing it. Extensions are stored in an
 * ExtensionBag and retrieved by their class name.
 */
interface ExtensionAttribute {}
