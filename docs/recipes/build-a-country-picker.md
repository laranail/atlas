# Build a country picker

A `<select>` of every country, grouped, with the value your schema stores.

```php
// Controller
return view('address', [
    'countries' => Atlas::form()->groupedOptions(),
]);
```

```blade
<select name="country">
    @foreach ($countries as $continent => $options)
        <optgroup label="{{ $continent }}">
            @foreach ($options as $code => $name)
                <option value="{{ $code }}">{{ $name }}</option>
            @endforeach
        </optgroup>
    @endforeach
</select>
```

The group label is the continent's name — `Africa`, not `AF` — because a person
reads it. Continents with no countries left after a filter are dropped rather
than rendered as an empty heading.

Flat, if you do not want groups:

```php
Atlas::form()->options();                        // ['AF' => 'Afghanistan', …]
Atlas::form()->options('iso3', 'officialName');  // different key, different label

Atlas::query()->inhabitedOnly()->form()->options();   // no Antarctica on a signup form
```

Reach for `Atlas::countriesGroupedByContinent()` instead when the option needs
more than a name — a flag, a dial code — since that one hands back records:

```blade
<option value="{{ $country->iso2 }}">{{ $country->flag() }} {{ $country->name }}</option>
```

Validate what comes back with the rule, not a length check — `UK` passes every
length check and is not a country code:

```php
'country' => ['required', new CountryCode],
```

See [form data](../tools/form-data.md) and
[validation](../tools/validation.md).

---
[← Docs index](../../README.md#documentation)
