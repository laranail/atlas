<?php

declare(strict_types=1);

use Simtabi\Laranail\Atlas\Core\Geo\Coordinates;
use Simtabi\Laranail\Atlas\Core\Exception\InvalidCoordinates;

// No TestCase, no container — src/Core is framework-free and this is the
// cheapest proof of it. A stray Illuminate import here fails the run.

it('keeps latitude and longitude in the order they were named', function (): void {
    $philadelphia = new Coordinates(latitude: 39.95, longitude: -75.16);

    expect($philadelphia->latitude)->toBe(39.95)
        ->and($philadelphia->longitude)->toBe(-75.16);
});

it('reads a longitude-first pair without asking the caller to flip it', function (): void {
    // GeoJSON, PostGIS and most mapping APIs order lon/lat; humans say lat/lon.
    // The flip happens once, here, with a name on it.
    $fromGeoJson = Coordinates::fromLonLat(-75.16, 39.95);

    expect($fromGeoJson->latitude)->toBe(39.95)
        ->and($fromGeoJson->longitude)->toBe(-75.16);
});

it('refuses a latitude beyond the poles', function (float $latitude): void {
    // The common way a swapped pair surfaces: a longitude in the latitude slot.
    // 39.95, -75.16 is Philadelphia; -75.16, 39.95 is empty ocean — both are
    // valid numbers, so only the range check can catch it, and only sometimes.
    expect(fn (): Coordinates => new Coordinates($latitude, 0.0))->toThrow(InvalidCoordinates::class);
})->with([91.0, -91.0, 180.0, -180.0]);

it('names the likely cause when a latitude is out of range', function (): void {
    expect(fn (): Coordinates => new Coordinates(-120.0, 40.0))
        ->toThrow(InvalidCoordinates::class, 'fromLonLat');
});

it('accepts the poles themselves', function (float $latitude): void {
    expect(new Coordinates($latitude, 0.0)->latitude)->toBe($latitude);
})->with([90.0, -90.0, 0.0]);

it('refuses values that are not finite', function (float $latitude, float $longitude): void {
    expect(fn (): Coordinates => new Coordinates($latitude, $longitude))->toThrow(InvalidCoordinates::class);
})->with([
    [NAN, 0.0],
    [0.0, NAN],
    [INF, 0.0],
    [0.0, -INF],
]);

it('wraps longitude rather than rejecting it', function (float $given, float $expected): void {
    // 181° east is a real place — it is 179° west. Rejecting it would fail on
    // arithmetic that legitimately crosses the antimeridian.
    expect(new Coordinates(0.0, $given)->normalisedLongitude())->toBe($expected);
})->with([
    [0.0, 0.0],
    [179.0, 179.0],
    [181.0, -179.0],
    [-181.0, 179.0],
    [360.0, 0.0],
    [540.0, -180.0],
]);

it('keeps the given longitude so eastward arithmetic stays readable', function (): void {
    $point = new Coordinates(0.0, 181.0);

    expect($point->longitude)->toBe(181.0)
        ->and($point->normalisedLongitude())->toBe(-179.0);
});

it('compares within a tolerance because these are floats', function (): void {
    $a = new Coordinates(39.95, -75.16);
    $b = new Coordinates(39.95 + 1e-12, -75.16);

    expect($a->equals($b))->toBeTrue()
        ->and($a->equals(new Coordinates(39.96, -75.16)))->toBeFalse();
});

it('treats a wrapped longitude as equal to its normalised twin', function (): void {
    expect(new Coordinates(0.0, 181.0)->equals(new Coordinates(0.0, -179.0)))->toBeTrue();
});

it('serialises with the longitude normalised', function (): void {
    expect(new Coordinates(10.0, 190.0)->toArray())
        ->toBe(['latitude' => 10.0, 'longitude' => -170.0]);
});
