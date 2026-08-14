# The REST API

Ten read-only endpoints, off by default. Every one is a `GET` and nothing
here writes.

## Switching it on

```php
// config/laranail/atlas.php
'api' => [
    'enabled'    => env('ATLAS_API', true),
    'prefix'     => env('ATLAS_API_PREFIX', 'api/atlas'),
    'version'    => 'v1',
    'middleware' => ['api', 'throttle:60,1'],
],
```

**Off means the routes are never registered**, not registered-then-blocked. A
disabled endpoint that still appears in `route:list` is one loosened middleware
group away from being live, and nobody reviewing that change would think to look
in a package's config. Verify with `php artisan route:list | grep atlas` — while
disabled, there is nothing to find.

`middleware` is yours. The package ships `['api', 'throttle:60,1']` as a
default, not as a security decision: **there is no authentication here**, and if
this endpoint should not be public, that is what the middleware stack is for.

## Endpoints

All paths below are relative to `{prefix}/{version}` — by default
`api/atlas/v1`.

| Method | Path | Answers |
|---|---|---|
| `GET` | `/countries` | The filtered, sorted list |
| `GET` | `/countries/{code}` | One country, by alpha-2, alpha-3 or numeric |
| `GET` | `/currencies` | Every ISO 4217 code in use |
| `GET` | `/languages` | Every ISO 639-3 code spoken |
| `GET` | `/continents` | Code → name |
| `GET` | `/regions` | Region names |
| `GET` | `/subregions` | Subregion names |
| `GET` | `/distance` | Between two points, two countries, or one of each |
| `GET` | `/ip/{ip}` | The country an address was allocated to |
| `GET` | `/describe` | Which source answered, and how current it is |

### `GET /countries`

| Parameter | Rule | Effect |
|---|---|---|
| `continent` | string | `inContinent()` |
| `region` | string | `inRegion()` |
| `subregion` | string | `inSubregion()` |
| `currency` | `CurrencyCode` | `usingCurrency()` |
| `language` | `LanguageCode` | `speakingLanguage()` — **ISO 639-3** |
| `search` | string | `whereNameContains()` |
| `inhabited` | boolean | `inhabitedOnly()` |
| `limit` | 1–250 | `take()` |

```
GET /api/atlas/v1/countries?continent=EU&currency=EUR&limit=5
```

```json
{
  "data": [
    {
      "iso2": "AT", "iso3": "AUT", "numeric": "040",
      "name": "Austria", "official_name": "Republic of Austria",
      "native_name": "Österreich",
      "continent": "EU", "region": "Europe", "subregion": "Western Europe",
      "flag": "🇦🇹",
      "currencies": ["EUR"], "currency": "EUR",
      "languages": ["bar"],
      "calling_codes": ["43"], "calling_code": "43",
      "tld": ".at",
      "coordinates": {"latitude": 47.588, "longitude": 14.140},
      "bounds": [1.2, 46.377222, 19, 49.016667]
    }
  ]
}
```

The response shape is a `CountryResource`, not `CountryRecord::toArray()`.
`CountryRecord` is a domain type and may gain fields; an endpoint that returned
whatever it happened to hold would publish each of those the moment it was
added, and there would be no version of the API where that was reviewed.

`limit` is capped at 250 and a larger value is a 422 rather than a silent
clamp — a caller who asked for 5000 has a wrong assumption worth telling them
about.

### `GET /countries/{code}`

**404** for a code that names no country, not an empty 200. Answering
`{"data": null}` makes a typo look like a country with no fields.

### `GET /distance`

Each side is independently either a `lat,lon` pair or a country code, and mixing
them is allowed.

```
GET /api/atlas/v1/distance?from=51.5074,-0.1278&to=48.8566,2.3522
GET /api/atlas/v1/distance?from=KE&to=TZ&unit=mi
```

```json
{
  "data": {
    "metres": 343556.53, "kilometres": 343.55, "miles": 213.47,
    "unit": "km", "value": 343.55, "formatted": "343.6 km"
  }
}
```

Validation names **which side** was wrong rather than reporting "from is
invalid": a caller who typed `51.5074,-0.1278x` is told about a malformed
coordinate, and one who typed `XX` about an unknown country.

**409**, not 422, when a country in the catalogue carries no centroid. Both
arguments were valid; the data has a gap, and 422 would blame the caller.

### `GET /ip/{ip}`

**200 with a `reason`**, never 404, when an address places nowhere. The question
was well formed and the answer is "nowhere":

| `reason` | Means |
|---|---|
| `reserved` | RFC 1918, loopback, link-local — in use on millions of networks in every country there is |
| `table_not_installed` | You have not [built the table](../installation.md#the-ip-table-is-built-not-shipped) — a deployment problem |
| `not_allocated` | A genuine registry gap |

Separating the middle one is the point. A bare `null` makes an unbuilt table
look exactly like an unallocated address.

A malformed address is a **422** naming the field. The route is deliberately
unconstrained: a `where()` pattern looks like validation and is not — anything
it rejects becomes a 404 saying the endpoint does not exist, while
`ff.ff.ff.ff` matches any plausible pattern and is still not an address.

### `GET /describe`

```json
{"data": {
  "provider": "…\\GeneratedPlaceRepository", "version": "2026-08-14",
  "available": true, "countries": 250,
  "distance": "haversine", "ip_ready": false
}}
```

On the API and not only in `doctor`, because a consumer caching responses needs
to know when the catalogue underneath them changed, and asking the server beats
guessing from a package version.

## The rules are the same objects you get

`CurrencyCode`, `LanguageCode`, `CountryCode` and `Coordinate` validate this API
and are exported for your own forms — so the package validates its own surface
with exactly what it hands out. See [validation](validation.md).

---
[← Docs index](../../README.md#documentation)
