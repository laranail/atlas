# Store a country on a model

Store the **ISO 3166-1 alpha-2 code**, a `char(2)`. Not the name, which
changes; not the numeric code, which nothing else in your stack speaks; not the
enum's case name.

```php
Schema::create('addresses', function (Blueprint $table): void {
    $table->char('country', 2);
});
```

## Cast it to the enum

```php
use Simtabi\Laranail\Atlas\Enums\Country;

class Address extends Model
{
    protected function casts(): array
    {
        return ['country' => Country::class];
    }
}
```

`Country` is a backed enum, so Laravel's own enum cast handles it — this package
ships no cast of its own for it.

```php
$address->country;              // Country::Kenya
$address->country->value;       // 'KE'
```

## Reach the data

The enum is a key and carries no data, so go through the facade for anything you
display:

```php
$record = Atlas::country($address->country->value);

$record->name;          // 'Kenya'
$record->flag();        // '🇰🇪'
$record->currency();    // 'KES'
```

## Validate on the way in

```php
'country' => ['required', new CountryCode],
```

The rule accepts alpha-2, alpha-3 and numeric, so an import that carries `KEN`
or `404` validates — normalise to alpha-2 before storing:

```php
$iso2 = Atlas::country($request->validated('country'))->iso2;
```

---
[← Docs index](../../README.md#documentation)
