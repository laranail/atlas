# Find the nearest thing

You have a point and a set of places with coordinates. You want the closest.

```php
use Simtabi\Laranail\Atlas\Core\Geo\Coordinates;
use Simtabi\Laranail\Atlas\Facades\Atlas;

$here = new Coordinates(51.5074, -0.1278);

$nearest = collect($branches)
    ->map(fn ($branch) => [
        'branch'   => $branch,
        'distance' => Atlas::distance($here, new Coordinates($branch->lat, $branch->lon)),
    ])
    ->sortBy(fn (array $row) => $row['distance']->in(DistanceUnit::Metres))
    ->first();

$nearest['distance']->format();   // '2.4 km'
```

Sort on a **single unit**, not on the `Distance` objects — they are value types,
not comparable by `<`. `isShorterThan()` exists for pairwise comparison.

## Narrowing by country first

If the set is large and spread across countries, `at()` gives you a cheap
shortlist:

```php
$candidates = Atlas::countriesAt($here);   // countries whose bounding box holds the point
```

Then filter your places to those countries before measuring. Remember this is a
**bounding-box** test, so it over-returns — which is fine for narrowing and
wrong for deciding jurisdiction. See
[querying](../tools/querying.md#points-and-boxes).

## Which formula

Haversine, the default, is accurate to about 0.5%. Over 2.4 km that is 12
metres, which does not change which branch is nearest.

Switch to Vincenty (`ATLAS_DISTANCE_FORMULA=vincenty`) only if you are doing
something where half a percent matters — and read
[the caveat about near-antipodal points](../tools/geo.md#vincenty-does-not-always-converge)
first.

---
[← Docs index](../../README.md#documentation)
