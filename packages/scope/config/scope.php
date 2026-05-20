<?php

declare(strict_types=1);

/**
 * Optional 'resolvers' key per axis:
 *
 *   'locale' => [
 *       'default'   => 'en',
 *       'scopes'    => ['en' => [], 'pl' => []],
 *       'resolvers' => [
 *           ['class' => \Markommerce\Scope\Resolver\Resolution\Builtin\CookieResolver::class, 'cookieName' => 'site_locale'],
 *           \Markommerce\Scope\Resolver\Resolution\Builtin\AcceptLanguageResolver::class,
 *           ['class' => \Markommerce\Scope\Resolver\Resolution\Builtin\StaticResolver::class, 'value' => 'en'],
 *       ],
 *   ],
 *
 * Resolvers run in declared order; first non-null hierarchy-valid result wins.
 * Missing or empty 'resolvers' key → axis always resolves to its default.
 */
return [
    'axes' => [
        'locale'  => ['default' => 'default', 'scopes' => ['default' => []]],
        'market'  => ['default' => 'default', 'scopes' => ['default' => []]],
        'channel' => ['default' => 'web',     'scopes' => ['web' => []]],
    ],
];
