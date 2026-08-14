# Querying

`Atlas::query()` returns a `Core\Country\CountryQuery` — an immutable fluent
builder over the catalogue.

```php
use Simtabi\Laranail\Atlas\Facades\Atlas;

Atlas::query()
    ->inContinent('EU')
    ->usingCurrency('EUR')
    ->sortedByName()
    ->get();
```

## Immutable

Every filter returns a **new** instance, so a partially-built query is safe to
hold, pass around and reuse:

```php
$european = Atlas::query()->inContinent('EU');

$euro    = $european->usingCurrency('EUR')->get();
$nonEuro = $european->where(fn ($c) => ! in_array('EUR', $c->currencies, true))->get();
// $european is unchanged
```

A shared builder that mutates is a bug waiting for a second caller.

## Filters

| Method | Effect |
|---|---|
| `inContinent(Continent\|string $continent)` | By continent code or enum |
| `inRegion(string $region)` | `Europe`, `Africa`, … |
| `inSubregion(string $subregion)` | `Western Europe`, `Eastern Africa`, … |
| `usingCurrency(string $code)` | ISO 4217 |
| `speakingLanguage(string $code)` | **ISO 639-3** — `swa`, not `sw` |
| `whereNameContains(string $needle)` | Accent- and case-insensitive |
| `inhabitedOnly()` | Drops the uninhabited territories |
| `containing(Coordinates $point)` | Whose bounding box holds the point |
| `where(callable $predicate)` | Anything else |

## Lookups

| Method | Returns |
|---|---|
| `findByName(string $name)` | `?CountryRecord` — exact name |
| `findByDialCode(string $dialCode)` | `?CountryRecord` — first match; `+` optional |
| `allByDialCode(string $dialCode)` | `list<CountryRecord>` — every match |

On the facade as `Atlas::countryByName()` and `Atlas::countryByDialCode()`. See
[phone numbers](phone.md) for the length rules that go with a dial code.

Filters are applied at the terminal, not as they are added, so the order you
call them in does not change the result or the cost.

### `whereNameContains` folds accents

`'reunion'` finds Réunion and `'cote'` finds Côte d'Ivoire, because a search box
that requires the accent finds nothing for most of the people typing into it.

Folding goes through ICU when `ext-intl` is present and a built-in table
otherwise — never `iconv('ASCII//TRANSLIT')`, whose output differs between glibc
and BSD, so the same query would return different results on a developer's Mac
and the Linux box it deploys to.

## Ordering and limiting

| Method | Effect |
|---|---|
| `sortedByName()` | By display name |
| `sortedByCode()` | By ISO alpha-2 |
| `take(int $limit)` | First N after sorting |

## Terminals

| Method | Returns |
|---|---|
| `get()` | `list<CountryRecord>` |
| `first()` | `?CountryRecord` |
| `find(string $code)` | `?CountryRecord` — any ISO form |
| `findOrFail(string $code)` | `CountryRecord`, or throws |
| `count()` | `int` |
| `isEmpty()` | `bool` |
| `options(string $key = 'iso2', string $label = 'name')` | `array<string, string>` |
| `groupedByContinent()` | `array<string, list<CountryRecord>>` |
| `regions()` / `subregions()` | `list<string>` |
| `currencies()` / `languages()` | `list<string>` |

`find()` returns null and `findOrFail()` throws, and both exist on purpose:
"is this a country code?" is a question you ask about user input, where a null
is cheaper than a try/catch; `findOrFail()` is for when the caller has already
decided which country they mean.

## Select boxes

```php
Atlas::options();
// ['AF' => 'Afghanistan', 'AX' => 'Åland Islands', …]  — sorted by label

Atlas::options('iso3', 'officialName');
// ['AFG' => 'Islamic Republic of Afghanistan', …]

Atlas::groupedByContinent();
// ['AF' => [CountryRecord, …], 'EU' => [...], …]  — optgroups
```

Defaults come from `laranail.atlas.presentation`; see
[configuration](../configuration.md#presentation).

## Worked examples

```php
// Countries speaking more than five languages
Atlas::query()->where(fn ($c) => count($c->languages) > 5)->get();

// Swahili-speaking countries
Atlas::query()->speakingLanguage('swa')->count();          // 4

// Everything matching "guinea" — there are four
Atlas::query()->whereNameContains('guinea')->get();

// Accents fold both ways
Atlas::query()->whereNameContains('reunion')->get();       // Réunion
Atlas::query()->whereNameContains('cote')->get();          // Côte d'Ivoire
```

## What the dataset does and does not carry

Worth knowing before you build on a field:

| Field | Reality |
|---|---|
| `currencies` | A list, but **at most one entry** — 249 of 250 have exactly one, and one has none |
| `languages` | Up to 15 |
| `callingCodes` | Up to 3 |
| `coordinates` | Present for 249 of 250 |
| `bounds` | Present for 249 of 250 |

`currency()` and `callingCode()` return the first entry, which for currency is
always the only one. If you need a country's *historical* or *secondary*
currencies, this dataset does not have them.

## Points and boxes

```php
use Simtabi\Laranail\Atlas\Core\Geo\Coordinates;

Atlas::at(new Coordinates(-1.2921, 36.8219));   // Nairobi
// → KE, MZ, RW, TZ, ZM
```

That result is not a bug and it is the reason to read this paragraph:
**`at()` and `containing()` answer from bounding boxes, not polygons.** Nairobi
sits inside the rectangle around Mozambique as well as the one around Kenya,
because a rectangle around a country of any interesting shape contains a lot of
other countries.

It is the right tool for narrowing a candidate list cheaply, and the wrong one
for deciding jurisdiction. If you need the actual answer, take the shortlist and
put it through a polygon test.



---
[← Docs index](../../README.md#documentation)
