# Phone numbers

Calling codes, per-country length rules, and a pattern that matches what people
actually type.

## Finding a country by its dial code

```php
use Simtabi\Laranail\Atlas\Facades\Atlas;

Atlas::countryByDialCode('+254');   // ?CountryRecord — Kenya
Atlas::countryByDialCode('254');    // the + is optional
Atlas::countryByName('Kenya');      // exact-name lookup
```

Calling codes are **not unique**. `countryByDialCode()` returns one, which is
fine for "which flag do I show"; use the query when you need all of them:

```php
Atlas::query()->allByDialCode('1');      // [CA, US]
Atlas::query()->allByDialCode('1242');   // [BS]
```

Note what that second call implies: the dataset records the North American
Numbering Plan's Caribbean members under their **full** codes — the Bahamas is
`1242`, not `1` — so `allByDialCode('1')` returns two countries rather than
twenty-odd. That is the right shape for a lookup (`+1242` unambiguously means
the Bahamas) and the wrong shape for the question "who shares +1", which this
package does not answer.

## `PhoneRules`

```php
use Simtabi\Laranail\Atlas\Core\Country\PhoneRules;

$rules = Atlas::phoneRules('KE');                      // ?PhoneRules
$rules = PhoneRules::forCallingCode('+254');           // or directly

$rules->callingCode;     // '254'
$rules->minLength;       // national-number digits, minimum
$rules->maxLength;       // maximum
$rules->exact;           // whether those bounds come from a real numbering plan
```

| Member | Answers |
|---|---|
| `acceptsNationalNumber(string $number): bool` | Is this a plausible length for the part **after** the calling code? Non-digits are stripped first |
| `acceptsInternationalNumber(string $number): bool` | Does a **full** number, calling code included, match |
| `internationalPattern(): string` | The regex the second one uses |
| `toArray()` / `jsonSerialize()` | Output |

> Both accept methods were once called `accepts()` and `matches()`, a distinction
> no call site could see. Passing a full `+254712345678` to the national one
> counts the country digits towards the length and rejects a valid number, so the
> names now say which half of the number they take.

## `exact` is the field that matters

```php
if (! $rules->exact) {
    // The bounds are E.164's own limits, not this country's numbering plan.
}
```

Where a numbering plan is well established, the length is exact and comes from a
source. Where there is no source, the bounds fall back to E.164's limits — 15
digits total, minus the calling code's length, with a floor of 4 — and `exact`
is **false**.

That flag exists so a caller can tell the two apart, because guessing would be
worse than declining to. A form that rejects a valid number because the package
invented a figure teaches people to distrust the form, and they stop reading it.

Rows are added to the table only with a source, never by inference from
examples.

## The pattern tolerates real input

```php
$rules->acceptsInternationalNumber('+254 712 345 678');    // true
$rules->acceptsInternationalNumber('254712345678');        // true
$rules->acceptsInternationalNumber('+254-712-345-678');    // true
$rules->acceptsInternationalNumber('+254 (712) 345 678');  // true
$rules->acceptsInternationalNumber('(254) 712 345 678');   // false  ← see below
```

Spaces, dashes, brackets and dots are allowed **between digits**, and the `+` is
optional. Rejecting `+254 712 345 678` for its spaces is the same mistake as
guessing a length: the number is right and the form says no.

> **The separators are permitted after the calling code, not around it.** The
> pattern anchors on `^\+?254`, so `+1 (555) 123-4567` matches — the brackets
> wrap the area code, which is where anyone actually puts them — while
> `(254) 712 345 678`, with the country code itself bracketed, does not. Strip
> to digits first if you accept input in that shape.

`acceptsNationalNumber()` strips non-digits before counting, so it and
`acceptsInternationalNumber()` agree about what counts as a digit.

## What this is not

Not libphonenumber. There is no carrier lookup, no number-type classification
(mobile vs landline vs premium), no formatting to national conventions, and no
validation of the subscriber portion against a numbering plan's internal
structure.

It answers "is this a plausible number for this country, and what is the
country's dial code" — which is what a signup form asks — and stops. If you need
the rest, use `giggsey/libphonenumber-for-php` and keep this for the catalogue.

---
[← Docs index](../../README.md#documentation)
