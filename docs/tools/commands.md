# Commands

One command, plus the two generators it tells you to run.

## `laranail::atlas.doctor`

```bash
php artisan laranail::atlas.doctor
php artisan laranail::atlas.doctor --strict
```

Answers the three questions that otherwise fail silently:

1. **Is a data source answering at all?** A configured provider whose data
   package is not installed returns an empty catalogue, and an empty catalogue
   makes every country look nonexistent rather than raising anything.
2. **How old is the data?** Countries change — codes are reassigned, names
   change, currencies are replaced. A dataset nobody has regenerated in two
   years is wrong in ways no test will catch. Older than a year warns.
3. **Is the IP table installed?** It is built rather than shipped, so
   `countryForIp()` returns null on a fresh install — indistinguishable from
   "not allocated" unless something says so.

```
 INFO  Data source.

  Provider  Simtabi\Laranail\Atlas\Adapters\Generated\GeneratedPlaceRepository
  Countries ............................................................ 250
  Dataset .......................................... rinvex/countries v9.1.0

 INFO  IP to country.

 WARN  No range table, so countryForIp() answers null for every address.
       Build one with tools/build-ip-table.php if you need it.

 INFO  Distance.

  Formula ........................................................ haversine
```

> `Dataset` is the **source** the shipped catalogue was generated from, not a
> build date — `resources/data/dataset-version.txt` records what the data came
> out of. `doctor` warns when that stamp parses as a date older than a year; a
> source string like the one above does not parse as one, so it never goes
> stale on its own and staleness has to be judged by regenerating.

| Exit | When |
|---|---|
| `0` | Everything checked, warnings allowed |
| `0` | Warnings present, without `--strict` |
| `1` | The data source is unavailable |
| `1` | Any warning, with `--strict` |

An absent IP table is a **warning, not a failure**: an application using atlas
for its country catalogue and nothing else is entitled to skip a table built
from five registry downloads. `--strict` is for the CI job that wants it built.

### No `atlas:doctor` alias

Artisan's command registry is a flat map. A generic `atlas:doctor` is a name any
package or application could also want, and the loser is replaced without a
word. The package claims only `laranail::atlas.doctor`, and a test asserts the
generic name is not registered.

> The `::` works because Symfony resolves an exact command name before its
> `:`-splitting lookup. Getting the name *past* `Command::validateName()` — whose
> pattern rejects the empty segment in `::` — is what
> `SupportsNamespacedNames` is for.

## The generators

Not Artisan commands. They are scripts, because they run at development time
against the package's own source tree, not against an application.

```bash
php tools/build-dataset.php      # rebuild countries.php + dataset-version.txt
php tools/generate-enums.php     # rebuild Country, Currency, Language
php tools/build-ip-table.php     # fetch the five RIR files, build the range table
php tools/deptrac-guard.php      # boundary check that also fails on parse errors
php tools/sync-check.php         # what `composer sync-check` runs
```

`composer sync-check` re-runs the enum generator with `--check` and fails if the
enums would change — so a regenerated dataset with un-regenerated enums cannot
be merged. See [enums](enums.md#generated-and-checked).

---
[← Docs index](../../README.md#documentation)
