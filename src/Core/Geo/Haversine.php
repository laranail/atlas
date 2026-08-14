<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Atlas\Core\Geo;

use Simtabi\Laranail\Atlas\Core\Contracts\DistanceCalculator;

/**
 * Great-circle distance on a sphere.
 *
 * The default, because the earth is not a sphere but is close enough for almost
 * every question a country catalogue is asked. The error against the true
 * ellipsoid is under about 0.5% — a few hundred metres over a hundred
 * kilometres — which does not change the answer to "is this within delivery
 * range" and is not good enough for surveying. {@see Vincenty} is there for the
 * second case.
 *
 * Uses `atan2` rather than `asin`. Both are algebraically correct, but `asin`
 * loses precision for nearly-antipodal points where its argument approaches 1,
 * and the two-argument form is well-conditioned across the whole range.
 */
final readonly class Haversine implements DistanceCalculator
{
    /**
     * Mean earth radius in metres, as adopted by the IUGG.
     *
     * "Mean" is doing real work: the earth's polar and equatorial radii differ
     * by about 21 km, so any single radius is a compromise and this one is the
     * standard choice.
     */
    private const float EARTH_RADIUS_METRES = 6_371_008.8;

    public function between(Coordinates $from, Coordinates $to): Distance
    {
        $latFrom = $from->latitudeInRadians();
        $latTo = $to->latitudeInRadians();

        $latDelta = $latTo - $latFrom;
        $lonDelta = $to->longitudeInRadians() - $from->longitudeInRadians();

        $a = sin($latDelta / 2) ** 2
            + cos($latFrom) * cos($latTo) * sin($lonDelta / 2) ** 2;

        // Clamp before the square root. Accumulated floating-point error can
        // push $a a hair above 1 for antipodal points, and sqrt(1 - 1.0000001)
        // is NAN — a distance that silently poisons every comparison it reaches.
        $a = min(1.0, max(0.0, $a));

        $angle = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return Distance::fromMetres(self::EARTH_RADIUS_METRES * $angle);
    }

    public function name(): string
    {
        return 'haversine';
    }
}
