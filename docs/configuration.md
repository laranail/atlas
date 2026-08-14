# Configuration

Every key in `config/laranail/atlas.php`, its environment variable, and what
happens when you get it wrong.

## The config namespace

Keys live under `laranail.atlas.*` and the published file goes to
`config/laranail/atlas.php` — nested, not flat. A bare `atlas` key in
`config/` is a plausible collision with an application's own, and Laravel's
config repository is a flat map where the loser is replaced silently.

```php
config('laranail.atlas.distance.formula');   // not config('atlas.…')
```

## Publishing

```bash
php artisan vendor:publish --tag="laranail::atlas-config"
```

You do not have to. The package merges its own defaults, so publish only to
override something — and a **partial file works**: keys you leave out keep
their packaged value.

## Keys

### `provider`

```php
'provider' => env('ATLAS_PROVIDER', 'generated'),
```

Which data source answers. One of `generated` (shipped, the default), `rinvex`
(needs `rinvex/countries`), or `remote` — which is **reserved and not
implemented**.

The value must be a case of the `Provider` enum. A name that is not a case never
resolves, so a config string cannot become a class name or a method name here.
Register your own with a closure:

```php
app(AtlasManager::class)->extend('acme', fn (): PlaceRepository => new AcmeRepository);
```

Through the manager, not the facade. `Atlas` proxies `AtlasService` — the query
API — and registering a data source is driver plumbing that happens once in a
provider, so it does not get a facade.

A closure and not a class name, deliberately: adding a data source is then a
deliberate act in application code rather than a string an operator — or, in a
multi-tenant install, a database row — can edit into a class.

### `cache`

```php
'cache' => [
    'enabled' => env('ATLAS_CACHE', true),
    'store'   => env('ATLAS_CACHE_STORE'),      // null = the default store
    'ttl'     => (int) env('ATLAS_CACHE_TTL', 1440),   // minutes
    'prefix'  => 'laranail.atlas',
],
```

The shipped dataset is static, so the derived lists — summaries, groupings,
select options — are pure functions of it and cache indefinitely in practice.
The TTL exists for a `remote` provider rather than for the shipped one.

### `presentation`

```php
'presentation' => [
    'label'  => env('ATLAS_LABEL', 'name'),   // name | official_name | native_name
    'key'    => env('ATLAS_KEY', 'iso2'),     // iso2 | iso3
    'locale' => env('ATLAS_LOCALE'),          // null follows app.locale
],
```

Defaults for the [form maps](tools/form-data.md) — `Atlas::form()->options()`
and friends. An unrecognised `label` falls back to `name` rather than reaching
into the record with an arbitrary key.

### `distance`

```php
'distance' => [
    'unit'    => env('ATLAS_DISTANCE_UNIT', 'km'),          // km | mi | m | nmi
    'formula' => env('ATLAS_DISTANCE_FORMULA', 'haversine'), // haversine | vincenty
],
```

`unit` only chooses what `format()` prints — `Distance` converts between all
four regardless. Long spellings work: `kilometres` and `kilometers` are the same
request as `km`, because the spelling of that word is not something a config
file should have to get right.

| Formula | Model | Accuracy | Cost |
|---|---|---|---|
| `haversine` | sphere | ~0.5% | one trig pass |
| `vincenty` | WGS-84 ellipsoid | ~0.5 mm | iterative |

Haversine is the default because the question this package is usually asked —
"how far is the nearest branch" — does not need the precision.

> **Vincenty does not converge for near-antipodal points.** The inverse formula
> oscillates and never settles. When that happens the answer falls back to the
> spherical one, which is defined everywhere, and `Vincenty::converged()`
> reports it rather than hiding it.

An unrecognised formula falls back to haversine: a typo is a config error, not
a reason to break the first page that measures a distance.
`Atlas::describe()['distance']` says which is actually in use.

### `ip`

```php
'ip' => [
    'enabled' => env('ATLAS_IP_LOOKUP', false),
    'table'   => null,     // null = the package's own resources/data
],
```

`table` points at a directory holding a built range table. See
[installation](installation.md#the-ip-table-is-built-not-shipped) — the table is
generated rather than shipped, so `countryForIp()` answers `null` until you
build one.

### `api`

```php
'api' => [
    'enabled'    => env('ATLAS_API', false),
    'prefix'     => env('ATLAS_API_PREFIX', 'api/atlas'),
    'version'    => 'v1',
    'middleware' => ['api', 'throttle:60,1'],
],
```

Off by default, and **off means the routes are never registered** — not
registered-then-blocked. A disabled endpoint sitting in `route:list` is one
loosened middleware group away from being live, and nobody reviewing that change
would think to look in a package's config. See [the API](tools/api.md).

### `chrono`

```php
'chrono' => [
    'enabled' => env('ATLAS_CHRONO', true),
],
```

Whether to use `laranail/chrono` when it is installed. The bridge is
`class_exists`-guarded either way, so `true` with chrono absent is not an error
— `ChronoBridge::isAvailable()` returns false and the calls throw a named
exception rather than a class-not-found.

## Environment variables

| Variable | Default |
|---|---|
| `ATLAS_PROVIDER` | `generated` |
| `ATLAS_CACHE` | `true` |
| `ATLAS_CACHE_STORE` | *(default store)* |
| `ATLAS_CACHE_TTL` | `1440` |
| `ATLAS_LABEL` | `name` |
| `ATLAS_KEY` | `iso2` |
| `ATLAS_LOCALE` | *(follows `app.locale`)* |
| `ATLAS_DISTANCE_UNIT` | `km` |
| `ATLAS_DISTANCE_FORMULA` | `haversine` |
| `ATLAS_IP_LOOKUP` | `false` |
| `ATLAS_API` | `false` |
| `ATLAS_API_PREFIX` | `api/atlas` |
| `ATLAS_CHRONO` | `true` |

---
[← Docs index](../README.md#documentation)
