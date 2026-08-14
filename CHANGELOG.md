# Changelog

All notable changes to `laranail/atlas` are documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/)
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

Initial scaffold. Extracted from `laranail/toolkit`'s `Modules\Atlas`, which was a façade over
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

- **`Core\Support\Text`** — case- and accent-folding that does not depend on the C library.
  `iconv('UTF-8', 'ASCII//TRANSLIT', …)` folds `São Tomé` to `Sao Tome` on glibc and to
  `S~ao Tom'e` on BSD, which breaks both search (`cote` finds nothing) and enum generation
  (`SAoTomEAndPrIncipe`). ICU where available, an explicit table otherwise, and a test asserting
  the two agree.

- Config at `config('laranail.atlas.*')`, published to `config/laranail/atlas.php` — the laranail
  convention, which `PackageServiceProvider` applies by default.

- `src/Core` is framework-free, enforced two ways: deptrac statically, and unit tests that never
  boot Laravel.
