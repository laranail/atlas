# Form data

`Atlas::form()` returns a `Core\Country\FormData` — the catalogue as `value => label`
maps, ready to hand to a `<select>`.

```php
use Simtabi\Laranail\Atlas\Facades\Atlas;

Atlas::form()->options();
// ['AF' => 'Afghanistan', 'AX' => 'Åland Islands', …]
```

## Why it is a separate object

Because the shapes differed and the names did not say so. `Atlas::options()` and
`Atlas::continents()` returned maps; `Atlas::regions()` beside them returned a
flat list. Three methods on one class, phrased identically, and the only way to
find out which you had was to `dd()` it.

The split is now the rule, not a convention: **everything behind `form()` returns
a map keyed by what the form submits; everything on the facade returns records or
plain lists.** You can tell which you are getting from the call site.

## The surface

| Method | Returns | Keyed by |
|---|---|---|
| `options(string $key = 'iso2', string $label = 'name')` | `array<string, string>` | ISO code |
| `groupedOptions(string $key = 'iso2', string $label = 'name')` | `array<string, array<string, string>>` | continent **name**, then ISO code |
| `continents()` | `array<string, string>` | continent code |
| `dialCodes()` | `array<string, string>` | ISO alpha-2 |
| `currencies()` | `array<string, string>` | ISO 4217 |
| `languages()` | `array<string, string>` | ISO 639-3 |
| `regions()` / `subregions()` | `array<string, string>` | the name itself |

`$key` is `iso2`, `iso3` or `numeric`; `$label` is `name`, `officialName` or
`nativeName`. Defaults come from `laranail.atlas.presentation` — see
[configuration](../configuration.md#presentation).

## Narrowing it

`form()` is a terminal on the query builder as well as a shortcut on the facade,
so any filter applies:

```php
Atlas::query()->inhabitedOnly()->form()->options();          // no Antarctica
Atlas::query()->inContinent('EU')->form()->options('iso3');
Atlas::query()->usingCurrency('EUR')->form()->dialCodes();
```

## Everything here sorts

By name, unless the query already chose an order. A select box in dataset order
looks broken to a reader who has no idea what the dataset's order is, and
remembering to sort at every call site is exactly the sort of thing nobody does.

```php
Atlas::query()->sortedByCode()->form()->options();   // your order is kept
```

## `groupedOptions()` labels its groups for a reader

Keyed by the continent's display name, not its code, because `NA` is not a
heading. Continents the filters emptied are **dropped** — an `<optgroup>` with
nothing under it renders as a heading followed by silence, which is worse than
its absence.

```blade
<select name="country">
    @foreach (Atlas::form()->groupedOptions() as $continent => $countries)
        <optgroup label="{{ $continent }}">
            @foreach ($countries as $code => $name)
                <option value="{{ $code }}">{{ $name }}</option>
            @endforeach
        </optgroup>
    @endforeach
</select>
```

Use `Atlas::countriesGroupedByContinent()` when you need the records rather than
the labels — a flag, a dial code, anything beyond the name.

## A country with no code for the chosen key is dropped

Kosovo has no ISO numeric. Without this, `options('numeric')` would collapse it —
and any future such country — into a single empty-string key, silently taking the
rest of them with it. So `options('numeric')` returns 249 entries where
`options('iso2')` returns 250, and `XK` is present in the second.

## `dialCodes()` is keyed by country, not by code

```php
Atlas::form()->dialCodes();
// ['CA' => 'Canada (+1)', 'KE' => 'Kenya (+254)', 'US' => 'United States (+1)', …]
```

Calling codes are shared — `+1` is the whole North American Numbering Plan — so
keying by the code would keep one country of twenty-five. The code is in the
label instead, because a bare `+1` in a select box tells a user nothing.

Submit the ISO code and resolve the dial code server-side with
`Atlas::country($code)->callingCode()`; see [phone numbers](phone.md) for the
length rules that go with it.

## `currencies()` and `languages()` are identity maps

Value and label are the same string. The dataset carries currency and language
*codes*, not names, and inventing display names here would mean shipping a second
register to drift against the first. Translate them in the application, or reach
for a package whose job that is.

---
[← Docs index](../../README.md#documentation)
