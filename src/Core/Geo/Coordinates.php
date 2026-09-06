<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Atlas\Core\Geo;

use Stringable;
use JsonSerializable;
use Simtabi\Laranail\Atlas\Core\Exception\InvalidCoordinates;

/**
 * A point on the earth, validated at construction.
 *
 * The order is (latitude, longitude) and it is named, because the two are
 * routinely swapped: GeoJSON, PostGIS and most mapping APIs order them
 * lon/lat, while humans and street addresses say lat/lon. A pair of bare
 * floats in a constructor is the single most common way that mistake ships,
 * and it is silent — 39.9, -75.2 is Philadelphia and -75.2, 39.9 is empty
 * ocean south of Africa, both perfectly valid numbers.
 *
 * Latitude is clamped to ±90 because it is a physical limit: there is no
 * 91° north. Longitude is *wrapped* rather than rejected because 181° east
 * is a real place — it is 179° west — and rejecting it would fail on
 * arithmetic that legitimately crosses the antimeridian.
 */
final readonly class Coordinates implements JsonSerializable, Stringable
{
    public function __construct(
        public float $latitude,
        public float $longitude,
    ) {
        if (is_nan($latitude) || is_nan($longitude) || is_infinite($latitude) || is_infinite($longitude)) {
            throw InvalidCoordinates::notFinite($latitude, $longitude);
        }

        if ($latitude < -90.0 || $latitude > 90.0) {
            throw InvalidCoordinates::latitudeOutOfRange($latitude);
        }
    }

    public function __toString(): string
    {
        return sprintf('%.6F,%.6F', $this->latitude, $this->normalisedLongitude());
    }

    /**
     * Build from a longitude-first pair, the order GeoJSON and PostGIS use.
     *
     * Exists so that reading such data does not require the caller to remember
     * to flip the arguments — the flip happens once, here, with a name on it.
     */
    public static function fromLonLat(float $longitude, float $latitude): self
    {
        return new self($latitude, $longitude);
    }

    /**
     * Longitude normalised into [-180, 180).
     *
     * The stored value is left as given, because arithmetic that walks east
     * past the antimeridian is easier to follow when the numbers keep
     * increasing. This is what to compare and display.
     */
    public function normalisedLongitude(): float
    {
        $wrapped = fmod($this->longitude + 180.0, 360.0);

        if ($wrapped < 0.0) {
            $wrapped += 360.0;
        }

        return $wrapped - 180.0;
    }

    public function latitudeInRadians(): float
    {
        return deg2rad($this->latitude);
    }

    public function longitudeInRadians(): float
    {
        return deg2rad($this->normalisedLongitude());
    }

    /**
     * Equality within a tolerance, because these are floats.
     *
     * The default of 1e-9 degrees is roughly 0.1 mm at the equator — far below
     * the precision of any source this package reads, so it means "the same
     * point" without asserting bit-identical arithmetic.
     */
    public function equals(self $other, float $tolerance = 1e-9): bool
    {
        return abs($this->latitude - $other->latitude) <= $tolerance
            && abs($this->normalisedLongitude() - $other->normalisedLongitude()) <= $tolerance;
    }

    /**
     * @return array{latitude: float, longitude: float}
     */
    public function toArray(): array
    {
        return [
            'latitude'  => $this->latitude,
            'longitude' => $this->normalisedLongitude(),
        ];
    }

    /**
     * @return array{latitude: float, longitude: float}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
