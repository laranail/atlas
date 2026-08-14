# Geo

Coordinates, bounding boxes, distance, and the two formulas — all under
`Core\Geo` and all framework-free.

## `Coordinates`

```php
use Simtabi\Laranail\Atlas\Core\Geo\Coordinates;

$london = new Coordinates(51.5074, -0.1278);
$fromGeoJson = Coordinates::fromLonLat(-0.1278, 51.5074);   // longitude first
```

`fromLonLat()` exists because GeoJSON and PostGIS put longitude first and every
codebase that reads them has flipped the arguments at least once. The flip
happens here, with a name on it.

| Member | Effect |
|---|---|
| `latitude` / `longitude` | As given |
| `normalisedLongitude(): float` | Wrapped into `[-180, 180)` |
| `latitudeInRadians()` / `longitudeInRadians()` | For the maths |
| `equals(self $other, float $tolerance = 1e-9): bool` | Float-safe comparison |
| `toArray()` / `jsonSerialize()` / `__toString()` | Output |

### Latitude is clamped, longitude is wrapped

The asymmetry is deliberate:

- **Latitude beyond ±90 throws.** There is no 91° north; it is a physical
  limit and a value past it is a bug upstream.
- **Longitude past ±180 is kept as given.** 181° east is a real place — it is
  179° west — and rejecting it would break arithmetic that legitimately walks
  east across the antimeridian. The stored value stays as passed, because
  numbers that keep increasing are easier to follow; `normalisedLongitude()` is
  what to compare and display.

`NAN` and `INF` throw on construction. A NaN coordinate propagates silently
through every calculation downstream, so it fails at the boundary instead.

## `Distance`

Returned by every measurement. It carries its unit rather than being a bare
float whose meaning was decided by a string argument several lines earlier.

```php
$d = Atlas::distance($london, $paris);

$d->kilometres();        // 343.556…
$d->miles();             // 213.478…
$d->nauticalMiles();
$d->in(DistanceUnit::Metres);
$d->format();            // '343.6 km'
$d->format(DistanceUnit::Miles, precision: 2);
$d->isShorterThan($other);
```

`DistanceUnit` has four cases: `Metres` (`m`), `Kilometres` (`km`), `Miles`
(`mi`), `NauticalMiles` (`nmi`).

## `BoundingBox`

```php
$box = $country->bounds;              // ?BoundingBox

$box?->contains($point);              // bool
$box?->centre();                      // Coordinates
$box?->crossesAntimeridian();         // bool
$box?->toBbox();                      // [west, south, east, north] — GeoJSON order
```

`crossesAntimeridian()` is not a curiosity. Fiji's box does, and a naive
`west < x && x < east` test says every point on the planet is outside it. The
containment check handles the wrap; the method is there so you can tell when it
matters.

249 of the 250 countries carry bounds.

## The two formulas

Configured by `laranail.atlas.distance.formula`; both implement
`Core\Contracts\DistanceCalculator`.

| | `Haversine` (default) | `Vincenty` |
|---|---|---|
| Model | Sphere | WGS-84 ellipsoid |
| Accuracy | ~0.5% | ~0.5 mm |
| Cost | One trig pass | Iterative |
| Always terminates | Yes | **No** |

### Vincenty does not always converge

The inverse formula oscillates rather than converging for **near-antipodal
points** — two places on opposite sides of the earth. It is a known property of
the algorithm, not an implementation bug.

When it happens, the answer falls back to the spherical one, which is defined
everywhere, and `Vincenty::converged()` reports it:

```php
$vincenty = new Vincenty;
$distance = $vincenty->between($a, $b);

$vincenty->converged();   // false → that answer is Haversine's
```

Reported rather than hidden, because half a millimetre of accuracy that
silently becomes half a percent is worse than half a percent you knew about.

Haversine is the default because the question this package is usually asked —
"how far is the nearest branch" — does not need the precision, and a geo helper
that hangs on two specific points is worse than one that is slightly imprecise
everywhere.

## Finding a country by point

```php
Atlas::at(new Coordinates(-1.2921, 36.8219));   // Nairobi → KE, MZ, RW, TZ, ZM
```

Five, because this is a **bounding-box** test. See
[querying](querying.md#points-and-boxes).

---
[← Docs index](../../README.md#documentation)
