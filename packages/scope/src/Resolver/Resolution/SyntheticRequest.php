<?php

declare(strict_types=1);

namespace Markommerce\Scope\Resolver\Resolution;

use Marko\Routing\Http\Request;

class SyntheticRequest
{
    public static function create(): Request
    {
        return new Request(
            server: [],
            query: [],
            post: [],
            body: '',
        );
    }
}
