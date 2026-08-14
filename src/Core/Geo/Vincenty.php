<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Atlas\Core\Geo;

use Simtabi\Laranail\Atlas\Core\Contracts\DistanceCalculator;

/**
 * Geodesic distance on the WGS-84 ellipsoid, by Vincenty's inverse formula.
 *
 * Accurate to about half a millimetre, against roughly 0.5% for {@see Haversine}
 * — worth it for surveying and boundary work, and not worth the iteration for
 * "is the nearest branch within 5 km".
 *
 * ## The failure mode this handles
 *
 * **Vincenty's inverse formula does not always converge.** For nearly-antipodal
 * points — two places on opposite sides of the earth — the iteration oscillates
 * and never settles. Implementations that ignore this either loop forever or
 * return whatever the last iteration held, which is not a distance at all.
 *
 * There is no correct answer to give in that case from this formula, so the
 * fallback is the spherical one, which is well-conditioned everywhere. That
 * costs accuracy exactly where accuracy was already impossible here, and it is
 * reported by `converged()` rather than hidden: a caller that must know whether
 * it got the ellipsoid answer can ask.
 */
final class Vincenty implements DistanceCalculator
{
    /** WGS-84 semi-major axis, metres. */
    private const float A = 6_378_137.0;

    /** WGS-84 flattening. */
    private const float F = 1 / 298.257223563;

    /** WGS-84 semi-minor axis, metres. */
    private const float B = (1 - self::F) * self::A;

    private const int MAX_ITERATIONS = 200;

    private const float CONVERGENCE = 1e-12;

    private bool $converged = true;

    public function __construct(
        private readonly Haversine $fallback = new Haversine,
    ) {}

    /**
     * Whether the last {@see between()} call reached the ellipsoid answer.
     *
     * False means the points were near-antipodal, the iteration did not settle,
     * and the returned distance came from the spherical fallback.
     */
    public function converged(): bool
    {
        return $this->converged;
    }

    public function between(Coordinates $from, Coordinates $to): Distance
    {
        $this->converged = true;

        $phi1 = $from->latitudeInRadians();
        $phi2 = $to->latitudeInRadians();
        $l = $to->longitudeInRadians() - $from->longitudeInRadians();

        $u1 = atan((1 - self::F) * tan($phi1));
        $u2 = atan((1 - self::F) * tan($phi2));

        $sinU1 = sin($u1);
        $cosU1 = cos($u1);
        $sinU2 = sin($u2);
        $cosU2 = cos($u2);

        $lambda = $l;
        $sinSigma = 0.0;
        $cosSigma = 0.0;
        $sigma = 0.0;
        $cos2SigmaM = 0.0;
        $cosSqAlpha = 0.0;

        for ($i = 0; $i < self::MAX_ITERATIONS; $i++) {
            $sinLambda = sin($lambda);
            $cosLambda = cos($lambda);

            $sinSigma = sqrt(
                ($cosU2 * $sinLambda) ** 2
                + ($cosU1 * $sinU2 - $sinU1 * $cosU2 * $cosLambda) ** 2,
            );

            // Coincident points. Returning zero here rather than dividing by it
            // two lines down, which would be NAN.
            if ($sinSigma === 0.0) {
                return Distance::fromMetres(0.0);
            }

            $cosSigma = $sinU1 * $sinU2 + $cosU1 * $cosU2 * $cosLambda;
            $sigma = atan2($sinSigma, $cosSigma);

            $sinAlpha = $cosU1 * $cosU2 * $sinLambda / $sinSigma;
            $cosSqAlpha = 1 - $sinAlpha ** 2;

            // Equatorial line: cosSqAlpha is zero and cos2SigmaM is undefined.
            // The series below is written so that zero is the right value.
            $cos2SigmaM = $cosSqAlpha === 0.0 ? 0.0 : $cosSigma - 2 * $sinU1 * $sinU2 / $cosSqAlpha;

            $c = self::F / 16 * $cosSqAlpha * (4 + self::F * (4 - 3 * $cosSqAlpha));

            $previous = $lambda;
            $lambda = $l + (1 - $c) * self::F * $sinAlpha
                * ($sigma + $c * $sinSigma * ($cos2SigmaM + $c * $cosSigma * (-1 + 2 * $cos2SigmaM ** 2)));

            if (abs($lambda - $previous) < self::CONVERGENCE) {
                return Distance::fromMetres($this->distanceFrom($sigma, $sinSigma, $cosSigma, $cos2SigmaM, $cosSqAlpha));
            }
        }

        // Near-antipodal. No amount of further iteration helps, so say so and
        // give the answer that is always defined.
        $this->converged = false;

        return $this->fallback->between($from, $to);
    }

    public function name(): string
    {
        return 'vincenty';
    }

    private function distanceFrom(
        float $sigma,
        float $sinSigma,
        float $cosSigma,
        float $cos2SigmaM,
        float $cosSqAlpha,
    ): float {
        $uSq = $cosSqAlpha * (self::A ** 2 - self::B ** 2) / self::B ** 2;

        $a = 1 + $uSq / 16384 * (4096 + $uSq * (-768 + $uSq * (320 - 175 * $uSq)));
        $b = $uSq / 1024 * (256 + $uSq * (-128 + $uSq * (74 - 47 * $uSq)));

        $deltaSigma = $b * $sinSigma * ($cos2SigmaM + $b / 4 * (
            $cosSigma * (-1 + 2 * $cos2SigmaM ** 2)
            - $b / 6 * $cos2SigmaM * (-3 + 4 * $sinSigma ** 2) * (-3 + 4 * $cos2SigmaM ** 2)
        ));

        return self::B * $a * ($sigma - $deltaSigma);
    }
}
