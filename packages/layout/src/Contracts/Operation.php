<?php

declare(strict_types=1);

namespace Markommerce\Layout\Contracts;

/**
 * Marker interface for layout extension operations.
 *
 * Every operation value object implements this interface to form a
 * closed vocabulary of typed mutations that can be applied to a resolved
 * layout tree by the extension compiler.
 */
interface Operation {}
