<?php

declare(strict_types=1);

use Simtabi\Laranail\Atlas\Adapters\Generated\GeneratedPlaceRepository;
use Simtabi\Laranail\Atlas\Core\Contracts\PlaceRepository;

function atlasRepository(): GeneratedPlaceRepository
{
    return new GeneratedPlaceRepository(dirname(__DIR__, 3) . '/resources/data');
}

it('satisfies the repository seam', function (): void {
    expect(atlasRepository())->toBeInstanceOf(PlaceRepository::class);
});

it('loads the shipped dataset', function (): void {
    expect(atlasRepository()->isAvailable())->toBeTrue()
        ->and(atlasRepository()->all())->toHaveCount(250);
});

it('stamps the source release so staleness is answerable', function (): void {
    expect(atlasRepository()->version())->toStartWith('rinvex/countries ');
});

it('finds a country by any of the three ISO forms', function (string $code): void {
    expect(atlasRepository()->find($code)?->name)->toBe('Kenya');
})->with([
    'alpha-2' => 'KE',
    'alpha-3' => 'KEN',
    'numeric' => '404',
    'lower case' => 'ke',
    'padded' => ' KE ',
]);

it('separates alpha-3 from numeric without being told which was meant', function (): void {
    // Both are three characters, so strlen cannot distinguish them. Numeric
    // codes are all digits and alpha-3 codes never are.
    expect(atlasRepository()->find('USA')?->iso2)->toBe('US')
        ->and(atlasRepository()->find('840')?->iso2)->toBe('US');
});

it('returns null rather than throwing for input that is not a country', function (string $code): void {
    expect(atlasRepository()->find($code))->toBeNull();
})->with(['ZZ', 'ZZZ', '999', '', '   ', 'K', 'KENYA']);

it('does not resolve an empty code to a country with no numeric', function (): void {
    // XK (Kosovo) is user-assigned and has no ISO numeric. Indexing it under an
    // empty string would make find('') return it — and find('') is what an
    // unfilled form field looks like.
    expect(atlasRepository()->find('XK')?->name)->toBe('Kosovo')
        ->and(atlasRepository()->find('XK')?->numeric)->toBe('')
        ->and(atlasRepository()->find(''))->toBeNull();
});

it('derives the flag from the code rather than storing it', function (): void {
    expect(atlasRepository()->find('KE')?->flag())->toBe('🇰🇪')
        ->and(atlasRepository()->find('JP')?->flag())->toBe('🇯🇵');
});

it('carries currencies, languages and calling codes', function (): void {
    $kenya = atlasRepository()->find('KE');

    expect($kenya?->currency())->toBe('KES')
        ->and($kenya?->callingCode())->toBe('254')
        ->and($kenya?->languages)->toContain('eng');
});

it('leaves a field empty rather than guessing when the source has nothing', function (): void {
    // Antarctica genuinely has no currency. An empty list is the honest answer;
    // a plausible default here would be a fabrication.
    expect(atlasRepository()->find('AQ')?->currencies)->toBe([])
        ->and(atlasRepository()->find('AQ')?->currency())->toBeNull();
});

it('gives every country a well-formed identity', function (): void {
    $malformed = [];

    foreach (atlasRepository()->all() as $iso2 => $country) {
        $ok = strlen($iso2) === 2
            && $country->iso2 === $iso2
            && strlen($country->iso3) === 3
            && $country->name !== ''
            && ($country->numeric === '' || strlen($country->numeric) === 3);

        if (! $ok) {
            $malformed[] = $iso2;
        }
    }

    expect($malformed)->toBe([]);
});

it('reports itself unavailable when the dataset is missing', function (): void {
    $empty = new GeneratedPlaceRepository('/nonexistent/atlas/data');

    expect($empty->isAvailable())->toBeFalse()
        ->and($empty->version())->toBeNull()
        ->and($empty->all())->toBe([])
        ->and($empty->find('KE'))->toBeNull();
});
