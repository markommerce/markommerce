<?php

declare(strict_types=1);

namespace Markommerce\Testing\Profile\Exceptions;

use Marko\Core\Exceptions\MarkoException;

/**
 * Thrown when fromInstalled() is called without an app config path.
 */
class MissingAppConfigPathException extends MarkoException
{
    public function __construct()
    {
        parent::__construct(
            message: 'StoreProfile::fromInstalled() requires an explicit app config path.',
            context: 'Neither $appConfigPath argument nor MARKO_APP_CONFIG_PATH env var was set.',
            suggestion: 'Pass the app config directory as the second argument: StoreProfile::fromInstalled($vendorDir, __DIR__ . \'/config\').'
                . ' Or set the MARKO_APP_CONFIG_PATH environment variable.',
        );
    }
}
