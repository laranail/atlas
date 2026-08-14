# Changelog

All notable changes to `laranail/atlas` are documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/)
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- **`Core\Country\FormData`**, reached as `Atlas::form()` or as a terminal on any query
  (`Atlas::query()->inhabitedOnly()->form()`). Everything a form needs, in one place and in one
  shape: `options()`, `groupedOptions()`, `continents()`, `dialCodes()`, plus map versions of
  `currencies()`, `languages()`, `regions()` and `subregions()`.

  `groupedOptions()` is new behaviour, not a rename — it returns `<optgroup>`-ready nested maps
  labelled by continent **name** (`Africa`, not `AF`, because a person reads an optgroup label) and
  drops continents a filter emptied, since a heading with nothing under it reads worse than no
  heading. `dialCodes()` is keyed by ISO code rather than by dial code: `+1` is the whole North
  American Numbering Plan, so keying by the code would keep one country of twenty-five.

### Changed

The rule behind all of it: **everything behind `form()` returns a `value => label` map; everything
on the facade returns records or plain lists.** Before, `options()` and `continents()` returned maps
while `regions()` beside them returned a list — three methods on one class, phrased identically,
with no way to tell which shape you had without running it. Note that the lists did not move, they
*forked*: `Atlas::regions()` still returns `list<string>` and `Atlas::form()->regions()` returns a
map, so no caller is worse off. See `docs/architecture.md`.

Breaking, and pre-1.0 is when this costs least:

| Was | Now |
|---|---|
| `Atlas::options()` | `Atlas::form()->options()` |
| `Atlas::continents()` | `Atlas::form()->continents()` |
| `CountryQuery::options()` | `CountryQuery::form()->options()` |
| `Atlas::groupedByContinent()` | `Atlas::countriesGroupedByContinent()` |
| `Atlas::at()` | `Atlas::countriesAt()` |
| `Atlas::distanceBetween()` | `Atlas::distanceBetweenCountries()` |
| `CountryRecord::phone()` | `CountryRecord::phoneRules()` |
| `CountryRecord::acceptsPhoneNumber()` | `CountryRecord::acceptsInternationalNumber()` |
| `PhoneRules::accepts()` | `PhoneRules::acceptsNationalNumber()` |
| `PhoneRules::matches()` | `PhoneRules::acceptsInternationalNumber()` |
| `PhoneRules::pattern()` | `PhoneRules::internationalPattern()` |
| `AtlasManager::available()` | `AtlasManager::availableProviders()` |
| `LocaleRegistry::described()` | `LocaleRegistry::detailed()` |

The phone pair earns its own note. `accepts()` took the national number and `matches()` took the
full one, a distinction no call site could see — and passing a full `+254712345678` to the first
counts the country digits towards the length and rejects a valid number. The names now say which
half of the number they take.

`describe()` is deliberately unchanged: it is the shared vocabulary of `doctor`, the `/describe`
endpoint and the docs, and renaming the method alone would leave the HTTP surface saying something
else.

No HTTP response shape changed. `/continents` still returns a code → name map; it is sourced from
`form()->continents()` now.

### Fixed

- **`doctor`'s staleness check never ran.** It asked `strtotime()` to age the whole version stamp,
  and the stamp was `rinvex/countries v9.1.0` — provenance, not a date. `strtotime()` returns
  `false` for that, so the guard short-circuited and the answer was "not stale" for every dataset
  the package has ever shipped. Not a wrong warning: no warning, ever, from one of the three
  questions the command exists to answer. A health check that cannot fail reads as one that passed.

  `dataset-version.txt` now carries both halves, date first — `2025-07-14 rinvex/countries v9.1.0`.
  The date is the **source's** release date, read from composer's `installed.json`, not the build
  date: rebuilding from an unchanged source produces byte-identical data, and a build date would
  reset the catalogue's age without any of its content getting newer.

  Parsing moved to `Core\Support\DatasetVersion`, which takes exactly one leading `YYYY-MM-DD` and
  nothing looser — `strtotime()` would happily accept `yesterday` — and which reports a stamp it
  cannot date as **undatable** rather than as current, the same answer a null version already got.
  It takes the cutoff as an argument, so the rule is unit-testable without a fixed clock.

  Two consequences worth knowing. The shipped catalogue is generated from `rinvex/countries v9.1.0`,
  released 2025-07-14, so `doctor` warns on it today — truthfully, and for the first time. And under
  `--strict` that warning is a failure, so a CI job pinned to an ageing source will now say so.

- **`vendor/bin/testbench laranail::atlas.doctor` could not find the command.** There was no
  `testbench.yaml`, so the skeleton auto-discovered every *installed* laranail package and not the
  one under development — leaving `laranail::atlas.doctor` as the single command missing from a list
  full of its siblings, which reads as a naming bug rather than a missing registration. The Pest
  suite never caught it because `tests/TestCase.php` registers the provider itself.

- `Atlas::extend()` never existed — the facade proxies `AtlasService`, and `extend()` is on
  `AtlasManager`. It was documented in `docs/configuration.md`, `docs/tools/data-sources.md`, the
  published config file and the "unknown provider" exception message, all of which now say
  `app(AtlasManager::class)->extend(...)`.
- The chrono note in the config file demonstrated `Country::KE->timezones()`. The enum carries no
  methods by design and the case is `Country::Kenya`; the example is now the bridge call it meant.

## [0.1.0] - 2026-08-14

### Added

Initial release. Extracted from `laranail/toolkit`'s `Modules\Atlas`, which was a façade over
`rinvex/countries` returning bare arrays shaped by whatever that package exposed — so the data
package was load-bearing in every call site, not just in the loader.

- **`Core\Contracts\PlaceRepository`** — the seam every data source satisfies. Four methods; the
  query layer sits above it rather than being reimplemented per adapter.

- **`Adapters\Generated`** — the default source, reading a dataset built from the ISO registers by
  `tools/build-dataset.php`. 250 countries in one flat PHP array, ~174 KB, no data package and no
  network. `rinvex/countries` ships ~17 MB to answer the same questions.

- **`Adapters\Rinvex`** — reads the live package for consumers who already carry it.
  `AdapterParityTest` asserts the two agree field-for-field across every country, because the point
  of the seam is that which source is configured must not be observable to a caller.

- **`Core\Geo\Coordinates`** — a validated point, constructed by named argument because lat/lon is
  routinely swapped: GeoJSON and PostGIS order them lon/lat, humans say lat/lon, and `39.9, -75.2`
  is Philadelphia while `-75.2, 39.9` is empty ocean. Both are valid numbers, so only the range
  check catches it, and only sometimes. `fromLonLat()` does the flip once, with a name on it.

  Latitude is bounded (there is no 91° north); longitude is **wrapped**, because 181° east is a real
  place and rejecting it would break arithmetic that crosses the antimeridian.

- **`Core\Geo\BoundingBox`** — extent, with the antimeridian case handled: a box whose west edge has
  a greater longitude than its east edge is not malformed, it crosses ±180°. Fiji and Russia both
  need one, and the naive `$lon >= $west && $lon <= $east` reports every point in such a box as
  outside it.

- **`Services\AtlasManager`** — the driver registry. Deliberately **not**
  `Illuminate\Support\Manager`, which resolves the driver named `foo` by calling `createFooDriver()`
  — interpolating a config value into a method name. `Enums\Provider` is the allow-list, and
  `extend()` takes a closure rather than a class name, so registering a source is a deliberate act
  in application code that a config edit cannot reach.

- **`Core\Country\CountryQuery`** — an immutable fluent query. The module this replaces exposed
  fourteen fixed methods, so any question it had not anticipated meant filtering its array output by
  hand at the call site. Filters are closures applied once at the terminal, so chaining five walks
  the records once and an unresolved query costs nothing.

- **`Services\LocaleRegistry`** — which translation locales an application ships. Fixes a real bug
  in the original: it scanned `resource_path('lang')`, which **Laravel abandoned in version 9**, so
  it returned an empty list on every modern application — a language switcher with nothing in it.
  It survived because the test creating that directory in `setUp()` then found what it had put
  there. Paths are injected, so the behaviour tests use a sandbox and cannot arrange the world they
  measure.

- **`Enums\Country` (250), `Enums\Currency` (156), `Enums\Language` (156)** — generated from the
  dataset by `tools/generate-enums.php`. The application enum this replaces carried 250 class
  constants *and* three ~240-arm `match` tables — `getCallingPrefix()`, `getFlagEmoji()`,
  `getName()`, about 800 lines of data pretending to be code. All three are dataset lookups here,
  and the flag is derived arithmetically from the ISO code.

- **`Core\Geo\Distance`, `DistanceUnit`, `Haversine`, `Vincenty`** — moved from `laranail/toolkit`'s
  `InteractsWithGeo::distanceBetween()`, which returned a bare `float` whose unit was chosen by a
  string argument several lines earlier. `$d > 100` could not be read without scrolling, and
  changing the unit at the call site silently rescaled every comparison below it. A `Distance`
  carries its own unit and stores metres, so two built differently still compare correctly.

  `Vincenty` is added for the cases the sphere is too coarse for — half a millimetre against ~0.5%.
  Its inverse formula **does not converge for near-antipodal points**: it oscillates and never
  settles, and implementations that ignore that either loop forever or return the last iteration,
  which is not a distance. Here it falls back to the sphere and `converged()` reports it.

  `Haversine` clamps the haversine argument before the square root, because accumulated error can
  push it a hair above 1 and `sqrt(1 - 1.0000001)` is `NAN` — a distance that silently poisons every
  comparison it reaches.

- **`Core\Network`** — `IpAddress`, `IpRange`, `IpRangeTable`, and offline `Atlas::countryForIp()`.

  IPv4 is 32-bit integers; **IPv6 is `inet_pton` binary compared with `strcmp`** — PHP has no
  unsigned 128-bit integer, and `inet_pton` output is big-endian, so lexicographic order equals
  numeric order. Verified rather than assumed: `strcmp` is unsigned (so `::1` sorts below `8000::`),
  NUL-safe (so `2001:db8::` compares whole), and needs no GMP.

  `isPrivate()` gets RFC 1918 right, which the org's existing validator does not: it declares the
  middle block as `172.16.0.0`–`172.16.255.255`, but that block is a slash-twelve ending at
  `172.31.255.255`, so fifteen of its sixteen sub-blocks read as public — and `172.17.0.0/16` is
  Docker's default bridge network. Ranges here are prefix lengths expanded arithmetically, so the
  arithmetic cannot disagree with the prefix. An IPv4-mapped IPv6 address is judged by the address
  it carries, which is a documented way past an SSRF filter otherwise.

  Parsing is `filter_var` only. Hand-rolled octet splitting accepts `01.02.03.04`, which some
  resolvers read as octal — so a blocklist and a resolver disagree about where it points.

  **The table is not shipped.** It is ~10 MB of registry delegation data that changes daily;
  `tools/build-ip-table.php` builds it, and `laranail.atlas.ip.enabled` is off by default. That data
  answers country and nothing else — no city, no ISP, no VPN flag.

- **`Bridges\Chrono`** — optional country-to-timezone, `class_exists`-guarded. chrono is `^8.5` and
  this package is `^8.4.1`, so requiring it would drag every consumer up for a feature most never
  ask for. Absent, it throws a message naming the package to install rather than a class-not-found
  three frames deeper. A test asserts chrono is named in exactly one directory, and a CI job
  installs it so the present-path is actually exercised.

- **`Core\Support\Text`** — case- and accent-folding that does not depend on the C library.
  `iconv('UTF-8', 'ASCII//TRANSLIT', …)` folds `São Tomé` to `Sao Tome` on glibc and to
  `S~ao Tom'e` on BSD, which breaks both search (`cote` finds nothing) and enum generation
  (`SAoTomEAndPrIncipe`). ICU where available, an explicit table otherwise, and a test asserting
  the two agree.

- Config at `config('laranail.atlas.*')`, published to `config/laranail/atlas.php` — the laranail
  convention, which `PackageServiceProvider` applies by default.

- `src/Core` is framework-free, enforced two ways: deptrac statically, and unit tests that never
  boot Laravel.

- **`Rules\CountryCode`, `CurrencyCode`, `LanguageCode`, `Coordinate`** — derived from the dataset
  rather than declared, so they stay true when it is regenerated and when the source is swapped. A
  `size:2` check accepts `UK`, which is the code people reach for and is not one; the message names
  `GB` rather than saying "invalid". `Coordinate` bounds latitude and deliberately does **not** bound
  longitude, and rejects `NAN`/`INF` — both pass `numeric` and then propagate silently through every
  distance calculation downstream.

- **A read-only REST API**, off by default: ten `GET` endpoints over countries, the flat catalogues,
  distance, IP lookup and `describe`. Routes are **not registered** unless
  `laranail.atlas.api.enabled` — off means absent, not registered-then-blocked, because a disabled
  endpoint sitting in `route:list` is one loosened middleware group from being live.

  The config block for this shipped before the endpoints did: `api.enabled`, `prefix`, `version` and
  `middleware` were all present while `Http/`, `Rules/` and `routes/` were empty directories, so
  setting `ATLAS_API=true` produced no routes and no error.

  Responses go through a `CountryResource` rather than `CountryRecord::toArray()`. The record is a
  domain type and may gain fields; an endpoint returning whatever it held would publish each of
  those the moment it was added.

- **`Console\DoctorCommand`** (`laranail::atlas.doctor`) — reports which source answered, how old its
  data is, and whether the IP table is installed. That last one because the table is built rather
  than shipped, so `countryForIp()` returns null on a fresh install and that is indistinguishable
  from "not allocated". No generic `atlas:doctor` alias; a test asserts the bare name is not claimed.

- **English validation messages**, published to `lang/vendor/laranail-atlas/`. Each says what *would*
  have been accepted, not only that the value was rejected.

### Notes for consumers

- **Languages are ISO 639-3** — `eng`, `swa`, `fra`, not `en`, `sw`, `fr`. That is what the dataset
  carries and what `Enums\Language` is generated from. Austria's is `bar` (Bavarian), which has no
  two-letter code at all.
- **`currencies` is a list that holds at most one entry.** 249 of 250 countries have exactly one and
  one has none. Historical and secondary currencies are not in this dataset.
- **`callingCode()` returns a bare `254`**, with no leading `+`.
- **`at()` and `containing()` are bounding-box tests, not polygon tests.** A point in Nairobi returns
  KE, MZ, RW, TZ and ZM. Right for narrowing a candidate list, wrong for deciding jurisdiction.
