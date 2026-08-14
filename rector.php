<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\SetList;

/**
 * Pinned to the **php84** set, matching this package's `^8.4.1 || ^8.5` floor.
 *
 * Not php85, which is what `laranail/chrono` uses — chrono's floor is `^8.5` and its value objects
 * are built on `clone ($this, [...])`. Atlas supports 8.4, so the 8.5 set would rewrite code into
 * syntax that parses on the newer CI job and fails on the older one, which is the quietest possible
 * way to break a supported version.
 *
 * Generated files are skipped: `tools/build-dataset.php --check` asserts the dataset matches what
 * the generator emits byte for byte, so a Rector rewrite would put the two permanently at odds.
 */
return RectorConfig::configure()
    ->withPaths([__DIR__ . '/src', __DIR__ . '/tests'])
    ->withSkip([
        __DIR__ . '/vendor',
        __DIR__ . '/resources/data',
        __DIR__ . '/tests/Fixtures',
    ])
    ->withPhpSets(php84: true)
    ->withSets([
        SetList::CODE_QUALITY,
        SetList::DEAD_CODE,
        SetList::TYPE_DECLARATION,
        SetList::EARLY_RETURN,
    ])
    ->withImportNames(removeUnusedImports: true);
