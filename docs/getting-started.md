# Getting started

The mental model, then the calls you will actually make.

## The mental model

Three things, and knowing which is which explains most of the API:

| | What it is | When to reach for it |
|---|---|---|
| **`Country` enum** | A typed *key* — 250 cases, `Country::Kenya` | A method signature, a match arm, a database column |
| **`CountryRecord`** | The *data* — names, currencies, languages, coordinates | Anything you display or compute with |
| **`Atlas` facade** | The way from one to the other | Everywhere |

The enum deliberately carries no data. The version this package replaces held
names, calling codes and flags as three ~240-arm `match` tables, which is data
wearing code's clothes: every correction meant editing PHP, and no two of the
tables could be checked against each other.

```php
use Simtabi\Laranail\Atlas\Enums\Country;
use Simtabi\Laranail\Atlas\Facades\Atlas;

$kenya = Atlas::country(Country::Kenya->value);

$kenya->name;            // 'Kenya'
$kenya->officialName;    // 'Republic of Kenya'
$kenya->flag();          // '🇰🇪'
$kenya->currency();      // 'KES'
$kenya->callingCode();   // '254'  — bare, no leading '+'
$kenya->languages;       // ['eng', 'swa']  ← ISO 639-3, not 'en'/'sw'
```

## Looking one up

`country()` takes any of the three ISO forms, in any case, so the same call
serves a form that submits `KE` and an import that carries `404`.

```php
Atlas::country('KE');        // alpha-2
Atlas::country('ken');       // alpha-3, case-insensitive
Atlas::country('404');       // numeric
Atlas::country('Narnia');    // null

Atlas::countryOrFail('XX');  // throws — use when the caller already knows
```

> `country()` returns null rather than throwing because "is this a country
> code?" is a question you ask about user input, and a null is cheaper than a
> try/catch. `countryOrFail()` is for when you have already decided.

## Querying

`query()` returns an immutable builder. Each filter returns a new instance, so
a partially-built query is safe to hold, pass and reuse.

```php
$euro = Atlas::query()
    ->inContinent('EU')
    ->usingCurrency('EUR')
    ->sortedByName()
    ->get();                 // list<CountryRecord>

Atlas::query()->speakingLanguage('swa')->count();          // 4
Atlas::query()->whereNameContains('guinea')->get();        // 4 of them
Atlas::query()->inhabitedOnly()->count();
Atlas::query()->where(fn ($c) => count($c->currencies) > 1)->get();
```

Terminals: `get()`, `first()`, `count()`, `isEmpty()`, `find()`,
`findOrFail()`, `groupedByContinent()`, `regions()`, `subregions()`,
`currencies()`, `languages()`, `form()`.

## A select box

Everything a form needs is behind `form()`, and everything behind `form()` is a
`value => label` map. The methods on the facade itself return records or plain
lists — so which shape you are getting is legible from the call rather than from
a `dd()`.

```php
Atlas::form()->options();                          // ['AD' => 'Andorra', 'AE' => …]
Atlas::form()->options('iso3', 'officialName');    // keyed and labelled differently
Atlas::form()->groupedOptions();                   // optgroups, labelled 'Africa', 'Europe', …
Atlas::form()->continents();                       // ['AF' => 'Africa', …]
Atlas::form()->dialCodes();                        // ['KE' => 'Kenya (+254)', …]

Atlas::query()->inhabitedOnly()->form()->options(); // any filter, same maps
```

The full surface is in [form data](tools/form-data.md).

## Distance

```php
use Simtabi\Laranail\Atlas\Core\Geo\Coordinates;

$london = new Coordinates(51.5074, -0.1278);
$paris  = new Coordinates(48.8566, 2.3522);

$d = Atlas::distance($london, $paris);

$d->kilometres();   // 343.556…
$d->miles();        // 213.478…
$d->format();       // '343.6 km'

Atlas::distanceBetweenCountries('KE', 'TZ');   // centroid to centroid, or null
```

`Distance` carries its unit rather than returning a bare float whose meaning
was decided by a string argument several lines earlier — which is what the
helper this replaces did.

## An IP address

```php
Atlas::countryForIp('41.90.0.1');   // ?CountryRecord — offline, no API key
```

Country and nothing else, and only after you have
[built the table](installation.md#the-ip-table-is-built-not-shipped).

## Where to go next

- [Configuration](configuration.md) — every key and its environment variable
- [Querying](tools/querying.md) — the full builder
- [Form data](tools/form-data.md) — everything behind `form()`
- [Geo](tools/geo.md) — coordinates, bounding boxes, the two formulas
- [Validation rules](tools/validation.md) — `CountryCode` and friends
- [The REST API](tools/api.md) — opt-in, read-only

---
[← Docs index](../README.md#documentation)
