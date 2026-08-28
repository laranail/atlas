<?php

declare(strict_types=1);

use Simtabi\Laranail\Atlas\Core\Country\FormData;
use Simtabi\Laranail\Atlas\Core\Region\Continent;
use Simtabi\Laranail\Atlas\Core\Country\CountryQuery;
use Simtabi\Laranail\Atlas\Adapters\Generated\GeneratedPlaceRepository;

function atlasForm(): FormData
{
    return FormData::over(new GeneratedPlaceRepository(dirname(__DIR__, 3) . '/resources/data'));
}

// -----------------------------------------------------------------------
// Every method here returns a value => label map. That is the whole point of
// the separation: on the service, regions() is a list; here it is a map, and
// which one you asked for is legible from the call.
// -----------------------------------------------------------------------

it('returns a map from every method', function (string $method): void {
    $result = atlasForm()->{$method}();

    expect($result)->toBeArray()->not->toBeEmpty()
        ->and(array_is_list($result))->toBeFalse();
})->with(['options', 'continents', 'dialCodes', 'currencies', 'languages', 'regions', 'subregions']);

it('sorts by name without being asked', function (): void {
    // A select box in dataset order looks broken to a reader who has no idea
    // what the dataset's order is.
    $names = array_values(atlasForm()->options());

    expect($names[0])->toBe('Afghanistan')
        ->and(end($names))->toBe('Zimbabwe');
});

it('keeps an order the caller chose over the default one', function (): void {
    // sortedByCode() puts Andorra first; the name sort puts Afghanistan there.
    // The form must not quietly re-sort what the query already ordered.
    $repository = new GeneratedPlaceRepository(dirname(__DIR__, 3) . '/resources/data');

    $byCode = CountryQuery::over($repository)->sortedByCode()->form()->options();

    expect(array_key_first($byCode))->toBe('AD');
});

it('groups into optgroups labelled for a reader, not by code', function (): void {
    // 'NA' is not a heading. A person reads an optgroup label.
    $grouped = atlasForm()->groupedOptions();

    expect($grouped)->toHaveKey('North America')
        ->and($grouped)->not->toHaveKey('NA')
        ->and($grouped['Africa']['KE'])->toBe('Kenya');
});

it('drops a continent the filters emptied rather than heading an empty list', function (): void {
    $grouped = atlasForm()->groupedOptions();

    foreach ($grouped as $options) {
        expect($options)->not->toBe([]);
    }

    expect(array_keys($grouped))->toHaveCount(count(Continent::cases()));
});

it('drops a country with no code for the chosen key rather than colliding on empty', function (): void {
    // Kosovo has no ISO numeric. Keying by numeric without this would collapse
    // it — and any future such country — into one empty-string key.
    $numeric = atlasForm()->options('numeric');

    expect($numeric)->toHaveCount(249)
        ->and($numeric)->not->toHaveKey('')
        ->and(atlasForm()->options('iso2'))->toHaveKey('XK');
});

it('labels a dial code with the country, because +1 alone says nothing', function (): void {
    // Keyed by country rather than by dial code: +1 is the whole North American
    // Numbering Plan, so keying by the code would keep one country of twenty-five.
    $codes = atlasForm()->dialCodes();

    expect($codes['KE'])->toBe('Kenya (+254)')
        ->and($codes['US'])->toBe('United States (+1)')
        ->and($codes['CA'])->toBe('Canada (+1)');
});

it('labels a currency with its own code, since the dataset carries no names', function (): void {
    // An honest identity map beats a second register invented here to drift
    // against the first.
    $currencies = atlasForm()->currencies();

    expect($currencies['KES'])->toBe('KES')
        ->and($currencies)->toHaveKeys(['EUR', 'USD']);
});
