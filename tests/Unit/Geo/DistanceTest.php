<?php

declare(strict_types=1);

use Simtabi\Laranail\Atlas\Core\Geo\Distance;
use Simtabi\Laranail\Atlas\Core\Geo\Vincenty;
use Simtabi\Laranail\Atlas\Core\Geo\Haversine;
use Simtabi\Laranail\Atlas\Core\Geo\Coordinates;
use Simtabi\Laranail\Atlas\Core\Geo\DistanceUnit;
use Simtabi\Laranail\Atlas\Core\Exception\InvalidCoordinates;

// Reference points, and the published geodesic distances between them.
function atlasLondon(): Coordinates
{
    return new Coordinates(51.5074, -0.1278);
}

function atlasParis(): Coordinates
{
    return new Coordinates(48.8566, 2.3522);
}

function atlasNairobi(): Coordinates
{
    return new Coordinates(-1.2921, 36.8219);
}

// -----------------------------------------------------------------------
// Distance — the value object
// -----------------------------------------------------------------------

it('converts between units through metres', function (): void {
    $d = Distance::from(1.0, DistanceUnit::Kilometres);

    expect($d->metres)->toBe(1000.0)
        ->and($d->kilometres())->toBe(1.0)
        ->and($d->in(DistanceUnit::Metres))->toBe(1000.0);
});

it('uses the exact international definitions', function (): void {
    // A mile is exactly 1609.344 m and a nautical mile exactly 1852 m by
    // agreement, so nothing is approximated by going through metres.
    expect(Distance::from(1.0, DistanceUnit::Miles)->metres)->toBe(1609.344)
        ->and(Distance::from(1.0, DistanceUnit::NauticalMiles)->metres)->toBe(1852.0);
});

it('compares two distances built in different units', function (): void {
    // The property the bare float lacked: a mile is longer than a kilometre
    // whatever each was constructed from.
    $mile = Distance::from(1.0, DistanceUnit::Miles);
    $kilometre = Distance::from(1.0, DistanceUnit::Kilometres);

    expect($kilometre->isShorterThan($mile))->toBeTrue()
        ->and($mile->isLongerThan($kilometre))->toBeTrue();
});

it('refuses a negative length', function (): void {
    expect(fn (): Distance => Distance::fromMetres(-1.0))
        ->toThrow(InvalidCoordinates::class, 'unsigned');
});

it('refuses a length that is not a number', function (float $value): void {
    expect(fn (): Distance => Distance::fromMetres($value))->toThrow(InvalidCoordinates::class);
})->with([NAN, INF]);

it('formats for display', function (): void {
    expect(Distance::from(343.556, DistanceUnit::Kilometres)->format())->toBe('343.6 km')
        ->and((string) Distance::from(5.0, DistanceUnit::Kilometres))->toBe('5 km');
});

// -----------------------------------------------------------------------
// DistanceUnit
// -----------------------------------------------------------------------

it('resolves a unit from any spelling anyone writes in config', function (string $written, DistanceUnit $expected): void {
    expect(DistanceUnit::resolve($written))->toBe($expected);
})->with([
    ['km', DistanceUnit::Kilometres],
    ['KM', DistanceUnit::Kilometres],
    ['kilometres', DistanceUnit::Kilometres],
    ['kilometers', DistanceUnit::Kilometres],
    [' Miles ', DistanceUnit::Miles],
    ['nmi', DistanceUnit::NauticalMiles],
    ['nautical miles', DistanceUnit::NauticalMiles],
]);

it('returns null for a unit that is not one', function (): void {
    expect(DistanceUnit::resolve('furlongs'))->toBeNull();
});

// -----------------------------------------------------------------------
// Haversine
// -----------------------------------------------------------------------

it('measures London to Paris within tolerance', function (): void {
    // The published geodesic distance is about 343.9 km. Haversine treats the
    // earth as a sphere and lands a few hundred metres short of that, which is
    // the documented ~0.5% and well inside this tolerance.
    $km = new Haversine()->between(atlasLondon(), atlasParis())->kilometres();

    expect($km)->toBeGreaterThan(340.0)->toBeLessThan(348.0);
});

it('is symmetric', function (): void {
    $there = new Haversine()->between(atlasLondon(), atlasParis())->metres;
    $back = new Haversine()->between(atlasParis(), atlasLondon())->metres;

    expect($there)->toBe($back);
});

it('is zero for a point and itself', function (): void {
    expect(new Haversine()->between(atlasNairobi(), atlasNairobi())->metres)->toBe(0.0);
});

it('does not return NAN for antipodal points', function (): void {
    // The clamp exists for this: accumulated error can push the haversine
    // argument a hair above 1, and sqrt(1 - 1.0000001) is NAN — a distance that
    // silently poisons every comparison it reaches.
    $metres = new Haversine()->between(new Coordinates(0.0, 0.0), new Coordinates(0.0, 180.0))->metres;

    expect(is_nan($metres))->toBeFalse()
        ->and($metres)->toBeGreaterThan(20_000_000.0);
});

it('measures across the antimeridian by the short way', function (): void {
    // 179E to 179W is two degrees apart, not 358.
    $km = new Haversine()->between(new Coordinates(0.0, 179.0), new Coordinates(0.0, -179.0))->kilometres();

    expect($km)->toBeLessThan(250.0);
});

// -----------------------------------------------------------------------
// Vincenty
// -----------------------------------------------------------------------

it('measures London to Paris more precisely than the sphere', function (): void {
    $vincenty = new Vincenty;
    $km = $vincenty->between(atlasLondon(), atlasParis())->kilometres();

    // Published geodesic: 343.9 km.
    expect($km)->toBeGreaterThan(343.5)->toBeLessThan(344.5)
        ->and($vincenty->converged())->toBeTrue();
});

it('disagrees with the sphere by less than one percent', function (): void {
    $h = new Haversine()->between(atlasLondon(), atlasParis())->metres;
    $v = new Vincenty()->between(atlasLondon(), atlasParis())->metres;

    expect(abs($h - $v) / $v)->toBeLessThan(0.01);
});

it('reports when it could not converge instead of returning a half-iterated number', function (): void {
    // Vincenty's inverse formula oscillates for near-antipodal points and never
    // settles. An implementation that ignores that either loops forever or
    // returns whatever the last iteration held — which is not a distance.
    $vincenty = new Vincenty;
    $metres = $vincenty->between(new Coordinates(0.0, 0.0), new Coordinates(0.5, 179.7))->metres;

    expect($vincenty->converged())->toBeFalse()
        ->and(is_nan($metres))->toBeFalse()
        ->and($metres)->toBeGreaterThan(19_000_000.0);
});

it('falls back to a value the sphere agrees with when it cannot converge', function (): void {
    $from = new Coordinates(0.0, 0.0);
    $to = new Coordinates(0.5, 179.7);

    expect(new Vincenty()->between($from, $to)->metres)
        ->toBe(new Haversine()->between($from, $to)->metres);
});

it('resets its convergence flag between calls', function (): void {
    // A sticky flag would report every later call as failed, which is worse
    // than not reporting at all.
    $vincenty = new Vincenty;

    $vincenty->between(new Coordinates(0.0, 0.0), new Coordinates(0.5, 179.7));
    expect($vincenty->converged())->toBeFalse();

    $vincenty->between(atlasLondon(), atlasParis());
    expect($vincenty->converged())->toBeTrue();
});

it('is zero for a point and itself without dividing by zero', function (): void {
    expect(new Vincenty()->between(atlasNairobi(), atlasNairobi())->metres)->toBe(0.0);
});

it('names itself for config and doctor output', function (): void {
    expect(new Haversine()->name())->toBe('haversine')
        ->and(new Vincenty()->name())->toBe('vincenty');
});
