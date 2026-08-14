<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Atlas\Core\Geo;

/**
 * The units a distance can be expressed in.
 *
 * An enum rather than a string, because the helper this replaces took
 * `string $unit = 'km'` and threw at runtime for anything it did not recognise.
 * A typo in a unit is a caught-at-authoring-time mistake here.
 */
enum DistanceUnit: string
{
    case Metres = 'm';
    case Kilometres = 'km';
    case Miles = 'mi';
    case NauticalMiles = 'nmi';

    /**
     * How many metres one of this unit is.
     *
     * Metres are the base because they are the SI unit and because every other
     * factor here is defined in terms of them by international agreement — the
     * mile and the nautical mile are both *exactly* these values, not
     * approximations, so nothing is lost converting through metres.
     */
    public function inMetres(): float
    {
        return match ($this) {
            self::Metres => 1.0,
            self::Kilometres => 1000.0,
            self::Miles => 1609.344,
            self::NauticalMiles => 1852.0,
        };
    }

    /**
     * Resolve a unit name, case-insensitively, or null.
     *
     * Accepts the long forms people write in config as well as the codes —
     * `kilometres`, `kilometers` and `km` are the same request, and the
     * spelling of the first two is not something a config file should have to
     * get right.
     */
    public static function resolve(string $value): ?self
    {
        return match (strtolower(trim($value))) {
            'm', 'metre', 'metres', 'meter', 'meters' => self::Metres,
            'km', 'kilometre', 'kilometres', 'kilometer', 'kilometers' => self::Kilometres,
            'mi', 'mile', 'miles' => self::Miles,
            'nmi', 'nm', 'nautical', 'nautical mile', 'nautical miles' => self::NauticalMiles,
            default => null,
        };
    }
}
