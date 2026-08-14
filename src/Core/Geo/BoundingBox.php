<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Atlas\Core\Geo;

use JsonSerializable;
use Simtabi\Laranail\Atlas\Core\Exception\InvalidCoordinates;

/**
 * An axis-aligned rectangle on the earth.
 *
 * Stored as two corners rather than four floats, so the lat/lon ordering
 * question is asked once — by {@see Coordinates} — instead of at every access.
 *
 * ## Antimeridian
 *
 * A box whose west edge has a greater longitude than its east edge is not
 * malformed: it crosses ±180°. Fiji and Russia both need one. `contains()`
 * handles it by testing the two halves rather than a single range, which is
 * the check most implementations get wrong — a naive `$lon >= $west && $lon <=
 * $east` reports every point in such a box as outside it.
 */
final readonly class BoundingBox implements JsonSerializable
{
    public function __construct(
        public Coordinates $southWest,
        public Coordinates $northEast,
    ) {
        if ($southWest->latitude > $northEast->latitude) {
            throw InvalidCoordinates::invertedBounds($southWest->latitude, $northEast->latitude);
        }
    }

    /**
     * Build from the GeoJSON `bbox` order: [west, south, east, north].
     */
    public static function fromBbox(float $west, float $south, float $east, float $north): self
    {
        return new self(
            new Coordinates($south, $west),
            new Coordinates($north, $east),
        );
    }

    public function contains(Coordinates $point): bool
    {
        if ($point->latitude < $this->southWest->latitude || $point->latitude > $this->northEast->latitude) {
            return false;
        }

        $west = $this->southWest->normalisedLongitude();
        $east = $this->northEast->normalisedLongitude();
        $lon = $point->normalisedLongitude();

        // West > east means the box wraps past ±180°, so the inside is the
        // union of two ranges rather than the span between them.
        return $west <= $east
            ? $lon >= $west && $lon <= $east
            : $lon >= $west || $lon <= $east;
    }

    public function crossesAntimeridian(): bool
    {
        return $this->southWest->normalisedLongitude() > $this->northEast->normalisedLongitude();
    }

    /**
     * The midpoint, which for a wrapping box is not the arithmetic mean.
     */
    public function centre(): Coordinates
    {
        $latitude = ($this->southWest->latitude + $this->northEast->latitude) / 2.0;

        $west = $this->southWest->normalisedLongitude();
        $east = $this->northEast->normalisedLongitude();

        if ($west <= $east) {
            return new Coordinates($latitude, ($west + $east) / 2.0);
        }

        // Walk east from the west edge across the seam, then wrap the result.
        return new Coordinates($latitude, new Coordinates(0.0, ($west + $east + 360.0) / 2.0)->normalisedLongitude());
    }

    /**
     * @return list<float> [west, south, east, north], the GeoJSON bbox order
     */
    public function toBbox(): array
    {
        return [
            $this->southWest->normalisedLongitude(),
            $this->southWest->latitude,
            $this->northEast->normalisedLongitude(),
            $this->northEast->latitude,
        ];
    }

    /**
     * @return list<float>
     */
    public function jsonSerialize(): array
    {
        return $this->toBbox();
    }
}
