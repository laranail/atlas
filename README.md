# laranail/atlas

[![Packagist](https://img.shields.io/packagist/v/laranail/atlas.svg?style=flat-square)](https://packagist.org/packages/laranail/atlas)
[![Tests](https://img.shields.io/github/actions/workflow/status/laranail/atlas/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/laranail/atlas/actions/workflows/tests.yml)
[![Static analysis](https://img.shields.io/github/actions/workflow/status/laranail/atlas/static-analysis.yml?branch=main&label=static%20analysis&style=flat-square)](https://github.com/laranail/atlas/actions/workflows/static-analysis.yml)
[![License MIT](https://img.shields.io/badge/license-MIT-blue.svg?style=flat-square)](LICENSE)

> Countries, currencies, languages and coordinates for Laravel — a generated ISO catalogue with a
> swappable data source, distance and bounding-box maths, and offline IP-to-country lookup.

Requires PHP `^8.4.1 || ^8.5` and Laravel `^13.0`. Companion to
[`laranail/chrono`](https://opensource.simtabi.com/documentation/laranail/chrono/), which answers
*when*; this one answers *where*.

## Install

```bash
composer require laranail/atlas
```

No data package required — 250 countries ship with the package as a flat PHP array that OPcache
holds as compiled opcodes.

## Quick start

```php
use Simtabi\Laranail\Atlas\Facades\Atlas;

$kenya = Atlas::country('KE');       // alpha-2, alpha-3 or numeric, any case

$kenya->name;          // 'Kenya'
$kenya->flag();        // '🇰🇪'
$kenya->currency();    // 'KES'

Atlas::query()->inContinent('EU')->usingCurrency('EUR')->sortedByName()->get();
Atlas::options();                                   // for a <select>
Atlas::distance($london, $paris)->format();         // '343.6 km'
Atlas::countryForIp('41.90.0.1');                   // offline, no API key
```

## The mental model

| | What it is | Reach for it |
|---|---|---|
| `Country` enum | A typed **key** — 250 cases | A signature, a match arm, a column cast |
| `CountryRecord` | The **data** | Anything you display or compute with |
| `Atlas` facade | The way from one to the other | Everywhere |

The enum carries no data on purpose. The module this replaces held names, calling codes and flags
as three ~240-arm `match` tables — data wearing code's clothes, where every correction meant editing
PHP and no two tables could be checked against each other.

## <a name="documentation"></a>Documentation

Hosted at **[opensource.simtabi.com/documentation/laranail/atlas](https://opensource.simtabi.com/documentation/laranail/atlas/)**.

### Guides
- [Installation](docs/installation.md) — requirements, what to publish, the table that is built rather than shipped
- [Getting started](docs/getting-started.md) — the mental model and the first calls
- [Configuration](docs/configuration.md) — every key and its environment variable
- [Architecture](docs/architecture.md) — the layering, what is enforced, and why the odd decisions are the way they are
- [Release](docs/release.md) — cutting a version, keeping generated data current

### Reference
- [Querying](docs/tools/querying.md) — the immutable builder, and what the dataset does and does not carry
- [Geo](docs/tools/geo.md) — coordinates, bounding boxes, and the formula that does not always converge
- [IP to country](docs/tools/ip-lookup.md) — offline lookup, and the three things a null means
- [Enums](docs/tools/enums.md) — three generated, one an allow-list
- [Phone numbers](docs/tools/phone.md) — dial codes, per-country length rules, and what `exact` means
- [Validation rules](docs/tools/validation.md) — `CountryCode`, `CurrencyCode`, `LanguageCode`, `Coordinate`
- [Data sources](docs/tools/data-sources.md) — the `PlaceRepository` seam and `extend()`
- [The REST API](docs/tools/api.md) — opt-in, read-only, ten endpoints
- [The chrono bridge](docs/tools/chrono-bridge.md) — country → timezones, optional
- [Commands](docs/tools/commands.md) — `doctor`, and the generators

### Recipes
- [Build a country picker](docs/recipes/build-a-country-picker.md)
- [Store a country on a model](docs/recipes/store-a-country-on-a-model.md)
- [Geolocate a request](docs/recipes/geolocate-a-request.md)
- [Find the nearest thing](docs/recipes/find-the-nearest.md)

## Stability

Pre-1.0, with immutable tags — every release is its own `v0.1.x` and none is ever re-pointed, so a
lockfile means something. Constraints resolve `^0.1`. New SemVer minors begin at 1.0.

Subdivisions, a hosted `remote` data source, and polygon-accurate point-in-country are candidates
for `v0.2`; see [the architecture notes](docs/architecture.md).

## Sister packages

- [`laranail/chrono`](https://opensource.simtabi.com/documentation/laranail/chrono/) — the *when* to this package's *where*
- [`laranail/ip-intel`](https://opensource.simtabi.com/documentation/laranail/ip-intel/) — city, ASN and threat signals, using this as its offline tier

## Contributing & security

Issues and PRs are welcome — see [CONTRIBUTING.md](CONTRIBUTING.md). Report vulnerabilities per
[SECURITY.md](SECURITY.md) (opensource@simtabi.com); participation follows the
[Code of Conduct](CODE_OF_CONDUCT.md).

## License

MIT © Simtabi LLC. See [LICENSE](LICENSE).
