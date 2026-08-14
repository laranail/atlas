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

- Config at `config('laranail.atlas.*')`, published to `config/laranail/atlas.php` — the laranail
  convention, which `PackageServiceProvider` applies by default.

- `src/Core` is framework-free, enforced two ways: deptrac statically, and unit tests that never
  boot Laravel.
