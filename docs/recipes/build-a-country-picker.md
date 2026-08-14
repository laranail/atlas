# Build a country picker

A `<select>` of every country, grouped, with the value your schema stores.

```php
// Controller
return view('address', [
    'countries' => Atlas::groupedByContinent(),
]);
```

```blade
<select name="country">
    @foreach ($countries as $continent => $records)
        <optgroup label="{{ $continent }}">
            @foreach ($records as $country)
                <option value="{{ $country->iso2 }}">
                    {{ $country->flag() }} {{ $country->name }}
                </option>
            @endforeach
        </optgroup>
    @endforeach
</select>
```

Flat, if you do not want groups:

```php
Atlas::options();                        // ['AF' => 'Afghanistan', …]
Atlas::options('iso3', 'officialName');  // different key, different label
```

Validate what comes back with the rule, not a length check — `UK` passes every
length check and is not a country code:

```php
'country' => ['required', new CountryCode],
```

See [querying](../tools/querying.md#select-boxes) and
[validation](../tools/validation.md).

---
[← Docs index](../../README.md#documentation)
