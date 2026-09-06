<?php

declare(strict_types=1);

use Rinvex\Country\CountryLoader;
use Simtabi\Laranail\Atlas\Core\Country\CountryRecord;
use Simtabi\Laranail\Atlas\Core\Support\DatasetVersion;
use Simtabi\Laranail\Atlas\Adapters\Rinvex\RinvexPlaceRepository;
use Simtabi\Laranail\Atlas\Adapters\Generated\GeneratedPlaceRepository;

/**
 * The claim `PlaceRepository` rests on: which source is configured must not be
 * observable to a caller.
 *
 * Stating that in a docblock costs nothing and proves nothing. The shipped
 * dataset is generated from rinvex/countries at build time, so if the two ever
 * disagree it is because the generator and the live adapter drifted — a change
 * to one that was not made to the other. This is the test that catches it.
 *
 * rinvex/countries is a `suggest`, not a dependency, so this skips when it is
 * absent rather than failing. CI installs it.
 */
beforeEach(function (): void {
    if (! class_exists(CountryLoader::class)) {
        test()->markTestSkipped('rinvex/countries is not installed.');
    }
});

function atlasGenerated(): GeneratedPlaceRepository
{
    return new GeneratedPlaceRepository(dirname(__DIR__, 3) . '/resources/data');
}

it('carries the same countries in both sources', function (): void {
    $generated = array_keys(atlasGenerated()->all());
    $live = array_keys(new RinvexPlaceRepository()->all());

    sort($generated);
    sort($live);

    expect($generated)->toBe($live);
});

it('answers identically for every country', function (): void {
    $generated = atlasGenerated()->all();
    $live = new RinvexPlaceRepository()->all();

    $differences = [];

    foreach ($generated as $iso2 => $record) {
        $other = $live[$iso2] ?? null;

        if ($other === null) {
            $differences[$iso2] = 'missing from the live adapter';

            continue;
        }

        $diff = array_keys(array_diff_assoc(
            array_map(serialize(...), $record->toArray()),
            array_map(serialize(...), $other->toArray()),
        ));

        if ($diff !== []) {
            $differences[$iso2] = implode(', ', $diff);
        }
    }

    expect($differences)->toBe([]);
});

it('resolves the same record through every lookup form', function (): void {
    $generated = atlasGenerated();
    $live = new RinvexPlaceRepository;

    foreach (['KE', 'KEN', '404', 'US', 'USA', '840', 'JP'] as $code) {
        expect($generated->find($code)?->iso2)
            ->toBe($live->find($code)?->iso2, "lookup [{$code}] disagrees");
    }
});

it('reports the live adapter as unversioned rather than inventing one', function (): void {
    // Inferring a version from a file mtime would be a number that looks
    // authoritative and is not. doctor renders null as unknown.
    expect(new RinvexPlaceRepository()->version())->toBeNull()
        ->and(DatasetVersion::parse((string) atlasGenerated()->version())->source)
        ->toStartWith('rinvex/countries ');
});

it('hydrates the same value object type from both', function (): void {
    expect(atlasGenerated()->find('KE'))->toBeInstanceOf(CountryRecord::class)
        ->and(new RinvexPlaceRepository()->find('KE'))->toBeInstanceOf(CountryRecord::class);
});
