# Validation rules

Four `ValidationRule` objects under `Rules\`. Each is derived from the dataset
rather than declared, so they stay true when it is regenerated and when the data
source is swapped.

```php
use Simtabi\Laranail\Atlas\Rules\CountryCode;
use Simtabi\Laranail\Atlas\Rules\CurrencyCode;
use Simtabi\Laranail\Atlas\Rules\LanguageCode;
use Simtabi\Laranail\Atlas\Rules\Coordinate;

public function rules(): array
{
    return [
        'country'  => ['required', new CountryCode],
        'currency' => ['required', new CurrencyCode],
        'language' => ['sometimes', new LanguageCode],
        'at'       => ['sometimes', new Coordinate],
    ];
}
```

These are the same objects the [REST API](api.md) validates itself with, so the
package cannot accept over HTTP something it would reject in PHP.

## `CountryCode`

Accepts ISO 3166-1 **alpha-2, alpha-3 or numeric**, in any case, because a form
that takes one and an import that takes another should not need two rules.

| Passes | Fails |
|---|---|
| `KE`, `ke`, `KEN`, `404` | `XX`, `ZZ`, `UK`, `KENYA`, `123` |

`UK` is the one worth naming. It is the code people reach for and it is not one
— Britain is `GB` — and it passes every length check, every regex, and every
`in:` list somebody typed from memory. The message says so:

> The country must be a country code this catalogue holds — ISO 3166-1 alpha-2
> (GB), alpha-3 (GBR) or numeric (826).

A message reading "the selected country is invalid" tells somebody staring at
`UK` nothing, and `GB` is the answer.

## `CurrencyCode`

An ISO 4217 code **some country in the catalogue actually uses**, derived from
the dataset rather than from a list.

That is the point of deriving it: `ZWL` and `SLL` both left circulation while
every hardcoded currency list in every application kept accepting them. When the
dataset is regenerated, the rule changes with it and nobody edits anything.

## `LanguageCode`

ISO **639-3** — three letters. `eng`, `swa`, `fra`; **not** `en`, `sw`, `fr`.

That is what the dataset carries and what the `Language` enum is generated from.
A rule that quietly accepted both forms would let a value be stored that nothing
downstream can match against a country. The message says which form it wants
rather than leaving somebody to find out.

## `Coordinate`

A `lat,lon` pair that `Core\Geo\Coordinates` will accept.

| Passes | Fails |
|---|---|
| `51.5074,-0.1278`, `0,0`, `-90,180`, `0,181` | `51.5074`, `here,there`, `NAN,0`, `0,INF`, `91,0` |

Two of those are deliberate and look like bugs:

- **`0,181` passes.** Longitude is not bounded, because 181° east is a real
  place — it is 179° west — and rejecting it breaks arithmetic that legitimately
  walks across the antimeridian. `Coordinates` wraps it into range.
- **`91,0` fails.** Latitude *is* bounded, because that one is a physical limit.
  There is no 91° north.

`numeric` alone is not enough: it accepts `NAN` and `INF` from a string payload,
and a NaN latitude propagates silently through every distance calculation
downstream rather than failing at the boundary where the bad input arrived.

## Empty values

Laravel skips a non-implicit rule for a string that trims to nothing, so `''`
reaches these rules **never** and passes unless the field is `required`. A
`null` is different — the key is present, so the rule runs and rejects it.

That asymmetry is the framework's, not this package's, and these rules do not
override it: making them implicit would make `sometimes` mean nothing. If a
field must be filled in, say `required`.

## Messages

Published with:

```bash
php artisan vendor:publish --tag="laranail::atlas-translations"
```

→ `lang/vendor/laranail-atlas/en/validation.php`. Each message says what
*would* have been accepted, not only that the value was rejected.

---
[← Docs index](../../README.md#documentation)
