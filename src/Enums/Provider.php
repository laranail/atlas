<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Atlas\Enums;

/**
 * The allow-list of data sources.
 *
 * This is not documentation of what happens to be registered — it is the gate.
 * `config('laranail.atlas.provider')` is resolved through `tryFrom()`, so a name
 * that is not a case here never reaches a factory, and a config value can never
 * become a class name or a method name.
 *
 * That rules out `Illuminate\Support\Manager`, which resolves `foo` by calling
 * `createFooDriver()`. The driver name arrives from a file an operator edits —
 * in a multi-tenant install, from a database row — and turning that into a
 * method call is a gadget waiting for a second bug. `AtlasManager::extend()`
 * takes a **closure**, so registering a source is a deliberate act in
 * application code.
 */
enum Provider: string
{
    /**
     * The dataset shipped with this package, built from the ISO registers by
     * `tools/build-dataset.php`. No data package, no network, no extension.
     */
    case Generated = 'generated';

    /**
     * `rinvex/countries`, for applications already carrying it or needing its
     * full long-list. An optional `suggest`; selecting it without the package
     * installed fails at resolve time with a message naming it.
     */
    case Rinvex = 'rinvex';

    /**
     * A hosted catalogue. Reserved — selecting it today throws rather than
     * silently falling back, because a fallback here means answering with data
     * the operator did not choose.
     */
    case Remote = 'remote';

    /**
     * Whether this source needs something that is not shipped with the package.
     */
    public function requiresExternalData(): bool
    {
        return match ($this) {
            self::Generated            => false,
            self::Rinvex, self::Remote => true,
        };
    }
}
