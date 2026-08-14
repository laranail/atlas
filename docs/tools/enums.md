# Enums

Four, three of them generated from the dataset and gated by a sync check.

| Enum | Cases | Backing value |
|---|---|---|
| `Country` | 250 | ISO 3166-1 alpha-2 — `'KE'` |
| `Currency` | 156 | ISO 4217 — `'KES'` |
| `Language` | 156 | ISO 639-3 — `'swa'` |
| `Provider` | 3 | The data-source allow-list |

## `Country` is a key, not a record

```php
use Simtabi\Laranail\Atlas\Enums\Country;

Country::Kenya->value;                       // 'KE'
Atlas::country(Country::Kenya->value);        // the CountryRecord
```

It deliberately carries **no data**. The version this package replaces held
names, calling codes and flags as three ~240-arm `match` tables — data wearing
code's clothes. Every correction meant editing PHP, no two tables could be
checked against each other, and an unknown value threw `UnhandledMatchError`
from whichever table you happened to hit first.

Use the enum where a *type* is wanted — a method signature, a match arm, a
column cast. Use `CountryRecord` for anything you display.

```php
function shipTo(Country $country): Rate { … }     // typed, exhaustive
```

## Case names are the English names

`Country::UnitedArabEmirates`, not `Country::AE`. The backing value is the code.
That way a `match` reads as prose and a typo is a compile-time error rather than
a two-letter string nobody notices.

`Language` and `Currency` case names are **upper-cased codes** — `Language::SWA`
— because a PHP case name is case-sensitive and `swa` beside `SWA` would read as
two languages. The backing value keeps the canonical lower-case form for
`Language` and upper for `Currency`.

## `Provider` is an allow-list

```php
enum Provider: string {
    case Generated = 'generated';
    case Rinvex    = 'rinvex';
    case Remote    = 'remote';      // reserved; not implemented
}
```

`laranail.atlas.provider` must be one of these. A name that is not a case never
resolves — which is the point: a config string an operator edits, or in a
multi-tenant install a database row, must never become a class name or a method
name. Registering your own source takes a closure, not a string. See
[data sources](data-sources.md).

## Generated, and checked

`Country`, `Currency` and `Language` are generated from
`resources/data/countries.php` by `tools/generate-enums.php`.

```bash
php tools/generate-enums.php            # regenerate
php tools/generate-enums.php --check    # fail if they would change
composer sync-check                     # what CI runs
```

The check is what makes the guarantee real. Without it, a regenerated dataset
with un-regenerated enums is a silent inconsistency: `Country::XK` exists in
code and nothing in the catalogue answers for it, or a new country is in the
data and has no case.

Generated files are excluded from Pint and Rector — a reformatter would put the
committed artefact and the generator permanently at odds.

## Why languages are 639-3

`eng` and `swa`, not `en` and `sw`. Two-letter 639-1 codes cover a few hundred
languages; 639-3 covers every one with a code, which is what a country's
language list actually needs — the dataset has Austria down as `bar`
(Bavarian), which has no 639-1 code at all.

Accepting both forms would mean shipping a mapping table and deciding which to
store, so the package takes one and says which — in the
[validation message](validation.md#languagecode) as well as here.

---
[← Docs index](../../README.md#documentation)
