<?php

declare(strict_types=1);

use Simtabi\Laranail\Atlas\Enums\Provider;

return [

    /*
    |--------------------------------------------------------------------------
    | Data source
    |--------------------------------------------------------------------------
    |
    | Read as `config('laranail.atlas.provider')` — this file publishes to
    | `config/laranail/atlas.php`, matching the `laranail::atlas.<command>` shape
    | commands use.
    |
    | `generated` is the shipped dataset: ~250 countries built from the ISO
    | registers by `tools/build-dataset.php`, versioned in
    | `resources/data/dataset-version.txt`, and requiring no data package at all.
    | It is the default because a country catalogue that needs a 17 MB dependency
    | to answer "what is KE called" is a poor trade.
    |
    | `rinvex` reads rinvex/countries instead, for applications already carrying
    | it or needing its full long-list. `remote` is reserved for a hosted
    | catalogue and is not implemented yet.
    |
    | The value must be a case of the Provider enum. A name that is not a case
    | never resolves — a config string cannot become a class name or a method
    | name here. Register your own with
    | `app(AtlasManager::class)->extend('name', fn () => …)`, which takes a
    | closure, so adding a source is a deliberate act in application code rather
    | than a string an operator can edit. Not on the Atlas facade: that proxies
    | the query API, and driver registration is not part of it.
    |
    */

    'provider' => env('ATLAS_PROVIDER', Provider::Generated->value),

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    |
    | The dataset is static, so the derived lists (summaries, groupings, select
    | options) are pure functions of it and cache indefinitely in practice. The
    | TTL is in minutes and exists for the `remote` provider rather than for the
    | shipped one.
    |
    | `store` is a cache store name from config/cache.php; null uses the default.
    | `prefix` namespaces every key this package writes.
    |
    */

    'cache' => [
        'enabled' => env('ATLAS_CACHE', true),
        'store' => env('ATLAS_CACHE_STORE'),
        'ttl' => (int) env('ATLAS_CACHE_TTL', 1440),
        'prefix' => 'laranail.atlas',
    ],

    /*
    |--------------------------------------------------------------------------
    | Presentation defaults
    |--------------------------------------------------------------------------
    |
    | `label` is which name a select box shows: name, official_name or
    | native_name. Anything else falls back to `name` rather than reaching into
    | the record with an arbitrary key.
    |
    | `key` chooses the option value: iso2 (the usual choice — it is what most
    | schemas store) or iso3.
    |
    | `locale` is null to follow app.locale.
    |
    */

    'presentation' => [
        'label' => env('ATLAS_LABEL', 'name'),
        'key' => env('ATLAS_KEY', 'iso2'),
        'locale' => env('ATLAS_LOCALE'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Distance
    |--------------------------------------------------------------------------
    |
    | `unit` is the default for display: km, mi, m or nmi. Long spellings work
    | too — `kilometres` and `kilometers` are the same request as `km`, because
    | the spelling of that word is not something a config file should have to
    | get right. `Atlas::distance()` returns a Distance object that converts
    | between all of them, so this only chooses what `format()` prints.
    |
    | `formula` is haversine or vincenty. Haversine treats the earth as a sphere
    | and is accurate to roughly 0.5% — fine for "how far is the nearest branch",
    | wrong for surveying. Vincenty is iterative over the WGS-84 ellipsoid,
    | accurate to about half a millimetre, and slower. Haversine is the default
    | because the question this package is usually asked does not need the
    | precision.
    |
    | Note that Vincenty's inverse formula does not converge for near-antipodal
    | points — it oscillates and never settles. When that happens the answer
    | falls back to the spherical one, which is defined everywhere, and
    | `Vincenty::converged()` reports it rather than hiding it.
    |
    | An unrecognised formula falls back to haversine. A typo here is a config
    | error, not a reason to break the first page that measures a distance;
    | `Atlas::describe()['distance']` says which one is actually in use.
    |
    */

    'distance' => [
        'unit' => env('ATLAS_DISTANCE_UNIT', 'km'),
        'formula' => env('ATLAS_DISTANCE_FORMULA', 'haversine'),
    ],

    /*
    |--------------------------------------------------------------------------
    | IP to country
    |--------------------------------------------------------------------------
    |
    | An offline lookup over an RIR-derived range table shipped with the package.
    | It answers country and nothing else — no city, no ISP, no ASN — because
    | that is the honest limit of freely redistributable registry data.
    |
    | `laranail/ip-intel` builds on this table for the richer questions. If you
    | only need a country, you do not need that package and you make no network
    | call.
    |
    | Disabled by default: the table is a few hundred KB of memory-mapped data
    | that most applications never ask for.
    |
    */

    'ip' => [
        'enabled' => env('ATLAS_IP_LOOKUP', false),
        'table' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP API
    |--------------------------------------------------------------------------
    |
    | Off by default, and off means the routes are never registered — not
    | registered-then-blocked. A disabled API should not appear in `route:list`
    | at all, so there is nothing to accidentally expose by loosening middleware
    | later.
    |
    | Every endpoint is read-only. `middleware` is yours to set; the shipped
    | default assumes the api group and a throttle, and `auth:sanctum` or an
    | equivalent belongs here if the data is not public to you.
    |
    */

    'api' => [
        'enabled' => env('ATLAS_API', false),
        'prefix' => env('ATLAS_API_PREFIX', 'api/atlas'),
        'version' => 'v1',
        'middleware' => ['api', 'throttle:60,1'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Chrono bridge
    |--------------------------------------------------------------------------
    |
    | `laranail/chrono` answers what timezones a country spans and what time it
    | is there. It is a suggest, not a dependency: the bridge is guarded by
    | class_exists, so with chrono absent
    | `app(ChronoBridge::class)->timezonesFor(Country::Kenya)` throws a message
    | naming the package to install rather than a class-not-found.
    |
    | Set false to keep it off even when chrono is installed.
    |
    */

    'chrono' => [
        'enabled' => env('ATLAS_CHRONO', true),
    ],

];
