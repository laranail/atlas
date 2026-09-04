<?php

declare(strict_types=1);

use Simtabi\Laranail\Atlas\Core\Geo\BoundingBox;
use Simtabi\Laranail\Atlas\Core\Geo\Coordinates;
use Simtabi\Laranail\Atlas\Core\Exception\InvalidCoordinates;

it('contains a point inside it', function (): void {
    $kenya = BoundingBox::fromBbox(west: 33.9, south: -4.7, east: 41.9, north: 5.0);

    expect($kenya->contains(new Coordinates(-1.29, 36.82)))->toBeTrue()  // Nairobi
        ->and($kenya->contains(new Coordinates(51.5, -0.12)))->toBeFalse(); // London
});

it('handles a box that crosses the antimeridian', function (): void {
    // Fiji spans ±180°, so its west edge has a *greater* longitude than its
    // east edge. The naive `$lon >= $west && $lon <= $east` reports every point
    // inside such a box as outside it — which is most implementations.
    $fiji = BoundingBox::fromBbox(west: 177.0, south: -21.0, east: -178.0, north: -16.0);

    expect($fiji->crossesAntimeridian())->toBeTrue()
        ->and($fiji->contains(new Coordinates(-18.14, 178.44)))->toBeTrue()   // Suva, east of 177
        ->and($fiji->contains(new Coordinates(-17.0, -179.5)))->toBeTrue()    // west of -178
        ->and($fiji->contains(new Coordinates(-18.0, 100.0)))->toBeFalse()    // the long way round
        ->and($fiji->contains(new Coordinates(-18.0, -100.0)))->toBeFalse();
});

it('excludes a point outside the latitude band even when the longitude fits', function (): void {
    $box = BoundingBox::fromBbox(west: 0.0, south: 0.0, east: 10.0, north: 10.0);

    expect($box->contains(new Coordinates(50.0, 5.0)))->toBeFalse();
});

it('refuses a box whose south edge is north of its north edge', function (): void {
    // Latitude cannot invert; longitude can, and that is the antimeridian case.
    expect(fn (): BoundingBox => BoundingBox::fromBbox(west: 0.0, south: 10.0, east: 10.0, north: 0.0))
        ->toThrow(InvalidCoordinates::class, 'antimeridian');
});

it('finds the centre of an ordinary box', function (): void {
    $centre = BoundingBox::fromBbox(west: 0.0, south: 0.0, east: 10.0, north: 20.0)->centre();

    expect($centre->latitude)->toBe(10.0)
        ->and($centre->normalisedLongitude())->toBe(5.0);
});

it('finds the centre of a wrapping box on the correct side of the seam', function (): void {
    // The arithmetic mean of 170 and -170 is 0 — the far side of the planet.
    // Walking east across the seam gives 180, which is where the box actually is.
    $centre = BoundingBox::fromBbox(west: 170.0, south: -10.0, east: -170.0, north: 10.0)->centre();

    expect($centre->latitude)->toBe(0.0)
        ->and(abs($centre->normalisedLongitude()))->toBe(180.0);
});

it('round-trips through the geojson bbox order', function (): void {
    expect(BoundingBox::fromBbox(1.0, 2.0, 3.0, 4.0)->toBbox())->toBe([1.0, 2.0, 3.0, 4.0]);
});
