<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Atlas\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Simtabi\Laranail\Atlas\Core\Geo\Coordinates;

/**
 * A `lat,lon` pair that {@see Coordinates} will accept.
 *
 * Latitude is bounded at ±90 because that is a physical limit — there is no
 * 91° north. **Longitude is not bounded**, and that is deliberate rather than
 * an omission: 181° east is a real place (it is 179° west), and rejecting it
 * breaks arithmetic that legitimately walks across the antimeridian.
 * `Coordinates` wraps longitude into range instead.
 *
 * `numeric` alone is not enough here. It accepts `NAN` and `INF` from a string
 * payload, and a NaN latitude propagates silently through every distance
 * calculation downstream rather than failing at the boundary where the bad
 * input arrived.
 */
final class Coordinate implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('laranail-atlas::validation.coordinate')->translate(['attribute' => $attribute]);

            return;
        }

        $parts = explode(',', trim($value));

        if (count($parts) !== 2) {
            $fail('laranail-atlas::validation.coordinate')->translate(['attribute' => $attribute]);

            return;
        }

        [$latitude, $longitude] = array_map(trim(...), $parts);

        if (! is_numeric($latitude) || ! is_numeric($longitude)) {
            $fail('laranail-atlas::validation.coordinate')->translate(['attribute' => $attribute]);

            return;
        }

        $lat = (float) $latitude;
        $lon = (float) $longitude;

        if (is_nan($lat) || is_nan($lon) || is_infinite($lat) || is_infinite($lon)) {
            $fail('laranail-atlas::validation.coordinate')->translate(['attribute' => $attribute]);

            return;
        }

        if ($lat < -90.0 || $lat > 90.0) {
            $fail('laranail-atlas::validation.latitude')->translate(['attribute' => $attribute]);
        }
    }
}
