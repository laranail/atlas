# The chrono bridge

Country → timezones, when [`laranail/chrono`](https://opensource.simtabi.com/documentation/laranail/chrono/)
is installed. Optional, and it stays optional.

## Using it

```php
use Simtabi\Laranail\Atlas\Bridges\Chrono\ChronoBridge;
use Simtabi\Laranail\Atlas\Enums\Country;

$bridge = app(ChronoBridge::class);

$bridge->isAvailable();                        // bool
$bridge->timezonesFor(Country::Kenya);         // list<string>
$bridge->primaryTimezoneFor('KE');             // ?string
```

Both take a `Country` enum or a plain code.

## It is a `suggest`, not a `require`

Taking chrono as a hard dependency would raise this package's PHP floor to
`^8.5` — chrono is the only package in the family that high — and cut atlas off
from every `^8.4` consumer for a feature most of them will not use.

So the bridge is `class_exists`-guarded — and because that floor stops chrono
being a dev dependency at all, **the default CI suite already runs without it**.
The absent-path is proved on every push, not by a special job.

The risk that leaves is the opposite one: the present-path never running, with
the bridge's own tests skipping silently forever and nobody noticing. A
`with-chrono` job on 8.5 installs it and **asserts the class is actually there**
before running the suite, so a skip cannot masquerade as a pass.

## What happens when chrono is not there

`isAvailable()` returns false, and the two lookups throw
`ChronoBridgeUnavailable` — a named exception with a message that says what to
install:

```php
ChronoBridgeUnavailable::notInstalled();   // composer require laranail/chrono
ChronoBridgeUnavailable::disabled();       // ATLAS_CHRONO=false
```

Two constructors rather than one, because "you have not installed it" and "you
switched it off" want different fixes and a single message would name the wrong
one half the time. A class-not-found fatal would name neither.

Guard with `isAvailable()` when the feature is optional in your application
too:

```php
$zones = $bridge->isAvailable() ? $bridge->timezonesFor($country) : [];
```

## Switching it off

```php
'chrono' => ['enabled' => env('ATLAS_CHRONO', true)],
```

`true` with chrono absent is not an error — the guard handles it. Set it false
to keep chrono installed for other reasons while making atlas ignore it.

## The division of labour

atlas answers **where**, chrono answers **when**. The bridge is the one place
they meet, and it is deliberately thin: atlas generates no timezone data of its
own, because chrono already ships a tzdata-pinned catalogue with a sync check
and a parity test, and a second copy in a second package is how the family ended
up with two `Timezone` enums that could drift apart.

---
[← Docs index](../../README.md#documentation)
