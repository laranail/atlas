<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Atlas\Core\Exception;

use InvalidArgumentException;
use Simtabi\Laranail\Atlas\Core\Contracts\AtlasException;

final class InvalidCoordinates extends InvalidArgumentException implements AtlasException
{
    public static function latitudeOutOfRange(float $latitude): self
    {
        return new self(sprintf(
            'Latitude %s is outside [-90, 90]. If this came from a lon/lat pair, the arguments are the '
            . 'wrong way round — use Coordinates::fromLonLat().',
            self::format($latitude),
        ));
    }

    public static function invertedBounds(float $south, float $north): self
    {
        return new self(sprintf(
            'A bounding box cannot have its south edge (%s) north of its north edge (%s). Longitude may '
            . 'invert — that means the box crosses the antimeridian — but latitude may not.',
            self::format($south),
            self::format($north),
        ));
    }

    public static function negativeDistance(float $metres): self
    {
        return new self(sprintf(
            'A distance cannot be %s metres. Distances are unsigned — direction is a property of the '
            . 'two points, not of the length between them.',
            self::format($metres),
        ));
    }

    public static function notFinite(float $latitude, float $longitude): self
    {
        return new self(sprintf(
            'Coordinates must be finite numbers; got (%s, %s).',
            self::format($latitude),
            self::format($longitude),
        ));
    }

    private static function format(float $value): string
    {
        return match (true) {
            is_nan($value) => 'NAN',
            is_infinite($value) => $value > 0 ? 'INF' : '-INF',
            default => (string) $value,
        };
    }
}
