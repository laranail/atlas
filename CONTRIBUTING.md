# Contributing

Thanks for helping improve `laranail/atlas`.

## Getting set up

```bash
composer install
composer test
composer lint
```

Requires PHP `^8.4.1 || ^8.5`. No extensions beyond the Laravel defaults, and no data package — the
shipped dataset is a plain PHP array in `resources/data/`.

## What must pass

- **Style** — `composer pint-fix` (Laravel Pint preset, `declare(strict_types=1)`).
- **Static analysis** — `composer phpstan` runs two configs: level 8 with larastan for the Laravel
  shell, and **level 10 with strict rules and no baseline for `src/Core`**. A baseline is just
  permission to be wrong.
- **Architecture** — `composer deptrac` proves `src/Core` references no `Illuminate\*` and no other
  `Simtabi\Laranail\*` package, and that the module DAG holds. It runs through
  `tools/deptrac-guard.php` rather than deptrac directly, because **deptrac exits 0 when it cannot
  parse a file** — a file it cannot read is a file with no rules applied, and the build still goes
  green.
- **Rector** — `composer rector` (dry run), pinned to the **`php84`** set. Not `php85`: this
  package supports 8.4, and the newer set would rewrite code into syntax that parses on one CI job
  and fails on the other.
- **Generated data** — `composer sync-check`. See below.
- **Tests** — `composer test` (Pest). Add tests for new behaviour.

## The `src/Core` boundary

`src/Core` is framework-free domain code: no Illuminate, no `laranail/*`, not even
`laranail/chrono`, which is a `suggest` reached through a `class_exists`-guarded bridge.

Two things enforce it, deliberately overlapping. deptrac checks it statically. And **unit tests
under `tests/Unit` do not boot Laravel at all** — a stray container dependency in Core fails the
test run, not just the architecture gate.

Above the boundary, the module DAG is: `Geo`, `Currency`, `Language` and `Region` depend on nothing
but `Shared`; `Country` depends on all four; `Network` depends on `Country`. Currency does not
depend on Country, because a currency does not belong to a country — several countries share one,
and the association is data that `Country` owns.

## Generated files

Two generators, chained. `tools/build-dataset.php` builds
`resources/data/countries.php` from `rinvex/countries`; `tools/generate-enums.php` builds
`src/Enums/{Country,Currency,Language}.php` from that dataset. **Never hand-edit any of them** —
change the generator and re-run it.

```bash
php tools/build-dataset.php            # write the dataset
php tools/build-dataset.php --check    # CI gate
php tools/generate-enums.php           # write the enums
php tools/generate-enums.php --check   # CI gate
composer sync-check                    # both
```

### Transliteration is deliberate, and `iconv` is banned here

Case names are ASCII, so accented country names have to be folded — and
`iconv('UTF-8', 'ASCII//TRANSLIT', …)` **must not** be used for it, because its output depends on
the C library. Measured on macOS, `é` becomes `'e` and `ã` becomes `~a`, so
`São Tomé and Príncipe` transliterates to `S~ao Tom'e and Pr'incipe` and the derived case name is
`SAoTomEAndPrIncipe` — against `SaoTomeAndPrincipe` on Linux. `Å` and `ç` survive on both, which is
what makes this the kind of difference nobody catches until a second machine regenerates.

`Core\Support\Text::transliterate()` is an explicit table for exactly this reason, and
`Text::fold()` (used for search) prefers ICU with the same table as its fallback. A `--check` gate
only means anything if two machines produce the same bytes.

`rinvex/countries` is a **dev-time input**, not a dependency. It ships ~17 MB across 252 long-list
JSON files, and this package needs a few fields from each — so it is read once, at build time, and
emitted as a flat PHP array that OPcache holds as compiled opcodes. `composer sync-check` skips when
the source package is absent, which is the ordinary state; CI installs it.

The `Rinvex` adapter is a separate thing: it reads the live package at runtime for consumers who
already have it. `tests/Unit/Adapters/AdapterParityTest.php` asserts the two agree field-for-field
across every country, because the whole point of `PlaceRepository` is that **which source is
configured must not be observable to a caller**. If they ever disagree, the generator and the
adapter drifted.

Generated files are excluded from Pint and Rector. A reformatter would put the committed artefact
and its generator permanently at odds.

## Pull requests

- Tests added or updated, and `composer test` passes
- `composer lint` is clean
- `CHANGELOG.md` updated under `## Unreleased` for user-facing changes
- Docs updated under `docs/` if behaviour or public API changed
- Commits follow [Conventional Commits](https://www.conventionalcommits.org/)
- New commands follow `laranail::atlas.<command>`. **No bare `atlas:` alias** — a short alias hands
  back exactly the collision the namespaced name exists to prevent.
