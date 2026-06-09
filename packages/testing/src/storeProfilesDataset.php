<?php

/**
 * Shared storeProfiles Pest dataset for the invariant matrix (task 011).
 *
 * Usage in a consumer package's tests/Datasets.php:
 *
 *   <?php
 *   declare(strict_types=1);
 *
 *   use Markommerce\Testing\Profile\StoreProfile;
 *
 *   $vendorDir = dirname(__DIR__, 1) . '/vendor';
 *
 *   dataset('storeProfiles', static function () use ($vendorDir): array {
 *       return [
 *           'simple'      => [StoreProfile::simple($vendorDir)],
 *           'two-locales' => [StoreProfile::singleMarketTwoLocales($vendorDir)],
 *           'two-markets' => [StoreProfile::twoMarketsTwoLocales($vendorDir)],
 *       ];
 *   });
 *
 * The dataset runs the decorated test body against all three StoreProfile presets:
 *   - simple: catalog + pgsql driver, no scope axes
 *   - two-locales: catalog + locale axis [en, de]
 *   - two-markets: catalog + market + locale axes [us/en, eu/de]
 *
 * Each test function decorated with ->with('storeProfiles') receives one argument:
 *   function (StoreProfile $profile): void { ... }
 *
 * This is a documentation-only file. Copy the dataset() call above into your
 * package's tests/Datasets.php (and adjust the $vendorDir path as needed).
 */
