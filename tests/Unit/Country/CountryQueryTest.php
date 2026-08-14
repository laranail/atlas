<?php

declare(strict_types=1);

use Simtabi\Laranail\Atlas\Adapters\Generated\GeneratedPlaceRepository;
use Simtabi\Laranail\Atlas\Core\Country\CountryQuery;
use Simtabi\Laranail\Atlas\Core\Country\CountryRecord;
use Simtabi\Laranail\Atlas\Core\Exception\UnknownCountry;
use Simtabi\Laranail\Atlas\Core\Geo\Coordinates;
use Simtabi\Laranail\Atlas\Core\Region\Continent;

function atlasQuery(): CountryQuery
{
    return CountryQuery::over(new GeneratedPlaceRepository(dirname(__DIR__, 3) . '/resources/data'));
}

// -----------------------------------------------------------------------
// Immutability
// -----------------------------------------------------------------------

it('never mutates the builder it was called on', function (): void {
    // The property a shared builder needs. Without it, handing $base to two
    // places and letting one add a filter silently changes the other's results.
    $base = atlasQuery();
    $narrowed = $base->inContinent(Continent::Africa);

    expect($narrowed)->not->toBe($base)
        ->and($base->count())->toBe(250)
        ->and($narrowed->count())->toBe(58);
});

it('lets one partially-built query fan out into several', function (): void {
    $african = atlasQuery()->inContinent(Continent::Africa);

    $shillings = $african->usingCurrency('KES')->count();
    $swahili = $african->speakingLanguage('swa')->count();

    expect($african->count())->toBe(58)
        ->and($shillings)->toBe(1)
        ->and($swahili)->toBeGreaterThan(1);
});

// -----------------------------------------------------------------------
// Filters
// -----------------------------------------------------------------------

it('filters by continent from the enum, a code or a name', function (Continent|string $continent): void {
    expect(atlasQuery()->inContinent($continent)->count())->toBe(58);
})->with([
    'enum' => Continent::Africa,
    'code' => 'AF',
    'lower-case code' => 'af',
    'name' => 'Africa',
    'mixed-case name' => 'aFrIcA',
]);

it('returns nothing for a continent that does not exist', function (): void {
    // Not "everything". Silently ignoring an unrecognised filter is how a typo
    // becomes a page listing the whole world.
    expect(atlasQuery()->inContinent('Atlantis')->get())->toBe([]);
});

it('separates continent from region', function (): void {
    // The two axes cross-cut; neither is a refinement of the other, and the
    // counts genuinely do not reconcile. South Georgia is region Americas on
    // continent Antarctica, and the US Minor Outlying Islands are region
    // Americas on continent Oceania — both because the UN geoscheme groups by
    // administration and continents group by landmass.
    //
    // An earlier version of this test asserted Americas == NorthAmerica +
    // SouthAmerica and failed by exactly those two. Bending it to 57 would have
    // hidden the reason; this asserts the reason.
    $americas = array_map(fn (CountryRecord $c): string => $c->iso2, atlasQuery()->inRegion('Americas')->get());

    $onAmericanContinents = array_map(
        fn (CountryRecord $c): string => $c->iso2,
        [
            ...atlasQuery()->inContinent(Continent::NorthAmerica)->get(),
            ...atlasQuery()->inContinent(Continent::SouthAmerica)->get(),
        ],
    );

    expect(array_values(array_diff($americas, $onAmericanContinents)))->toBe(['GS', 'UM'])
        ->and(array_diff($onAmericanContinents, $americas))->toBe([]);
});

it('matches any of a country currencies, not only the first', function (): void {
    // Panama's balboa is listed first and the US dollar is also legal tender.
    // A query for USD that missed it would be wrong.
    $usd = atlasQuery()->usingCurrency('USD')->get();
    $codes = array_map(fn (CountryRecord $c): string => $c->iso2, $usd);

    expect($codes)->toContain('US')
        ->and(count($usd))->toBeGreaterThan(1);
});

it('finds a country by an accented name typed without accents', function (): void {
    // Without folding, a country whose name a plain keyboard cannot produce is
    // unreachable by search.
    $names = array_map(
        fn (CountryRecord $c): string => $c->iso2,
        atlasQuery()->whereNameContains('cote')->get(),
    );

    expect($names)->toContain('CI');
});

it('searches all three name forms', function (): void {
    expect(atlasQuery()->whereNameContains('Republic of Kenya')->first()?->iso2)->toBe('KE');
});

it('ignores an empty search rather than matching nothing', function (): void {
    expect(atlasQuery()->whereNameContains('   ')->count())->toBe(250);
});

it('drops the uninhabited continent on request', function (): void {
    $all = atlasQuery()->count();
    $inhabited = atlasQuery()->inhabitedOnly()->count();

    expect($all - $inhabited)->toBe(5)
        ->and(array_map(fn (CountryRecord $c): string => $c->iso2, atlasQuery()->inhabitedOnly()->get()))
        ->not->toContain('AQ');
});

it('composes filters', function (): void {
    $result = atlasQuery()
        ->inContinent(Continent::Europe)
        ->usingCurrency('EUR')
        ->sortedByName()
        ->get();

    expect(count($result))->toBeGreaterThan(15);

    foreach ($result as $country) {
        expect($country->continent)->toBe('EU')
            ->and($country->currencies)->toContain('EUR');
    }
});

it('takes an inline predicate for a question it does not anticipate', function (): void {
    $landlocked = atlasQuery()->where(fn ($c): bool => $c->tld === '.ke')->get();

    expect($landlocked)->toHaveCount(1)
        ->and($landlocked[0]->iso2)->toBe('KE');
});

it('finds countries whose bounding box contains a point', function (): void {
    // Nairobi. A box is not a border, so this is a pre-filter — but Kenya must
    // be in the answer.
    $at = atlasQuery()->containing(new Coordinates(-1.29, 36.82))->get();

    expect(array_map(fn (CountryRecord $c): string => $c->iso2, $at))->toContain('KE');
});

// -----------------------------------------------------------------------
// Ordering, limiting, terminals
// -----------------------------------------------------------------------

it('sorts by name rather than by byte', function (): void {
    // strcmp compares bytes, which puts every accented name after every
    // unaccented one — Åland after Zimbabwe — an ordering no reader recognises.
    $names = array_map(fn (CountryRecord $c): string => $c->name, atlasQuery()->sortedByName()->get());

    $aland = array_search('Åland Islands', $names, true);
    $zimbabwe = array_search('Zimbabwe', $names, true);

    expect($aland)->toBeInt()
        ->and($zimbabwe)->toBeInt()
        ->and($aland)->toBeLessThan($zimbabwe);
})->skip(
    ! extension_loaded('intl'),
    'Byte ordering is the documented fallback without ext-intl.',
);

it('falls back to a defined order when the collator is unavailable', function (): void {
    // Whatever the platform, the order must be total and stable — a list that
    // reorders between requests is worse than one ordered oddly.
    $first = array_map(fn (CountryRecord $c): string => $c->name, atlasQuery()->sortedByName()->get());
    $second = array_map(fn (CountryRecord $c): string => $c->name, atlasQuery()->sortedByName()->get());

    expect($first)->toBe($second)->toHaveCount(250);
});

it('limits without changing the order', function (): void {
    $first = atlasQuery()->sortedByCode()->take(3)->get();

    expect($first)->toHaveCount(3)
        ->and($first[0]->iso2)->toBe('AD');
});

it('treats a negative limit as none rather than as an error', function (): void {
    expect(atlasQuery()->take(-5)->get())->toBe([]);
});

it('answers first and isEmpty without walking the whole set twice', function (): void {
    expect(atlasQuery()->inContinent(Continent::Africa)->first()?->continent)->toBe('AF')
        ->and(atlasQuery()->inContinent('Atlantis')->isEmpty())->toBeTrue()
        ->and(atlasQuery()->isEmpty())->toBeFalse();
});

it('looks a country up regardless of the filters in play', function (): void {
    // A lookup is not a query. Applying a half-built query's filters to
    // find('KE') would be surprising.
    expect(atlasQuery()->inContinent(Continent::Europe)->find('KE')?->name)->toBe('Kenya');
});

it('throws for a code the caller has already committed to', function (): void {
    expect(fn (): CountryRecord => atlasQuery()->findOrFail('ZZ'))
        ->toThrow(UnknownCountry::class, 'ZZ');
});

// -----------------------------------------------------------------------
// Presentation terminals
// -----------------------------------------------------------------------

it('builds select options sorted by name by default', function (): void {
    $options = atlasQuery()->options();

    expect($options)->toHaveCount(250)
        ->and(array_key_first($options))->toBeString()
        ->and($options['KE'])->toBe('Kenya');
});

it('builds options keyed by any ISO form', function (): void {
    expect(atlasQuery()->options('iso3')['KEN'])->toBe('Kenya')
        ->and(atlasQuery()->options('numeric')['404'])->toBe('Kenya');
});

it('drops a country with no code for the chosen key rather than colliding on empty', function (): void {
    // Kosovo has no ISO numeric. Keying by numeric without this would collapse
    // it — and any future such country — into one empty-string key.
    $numeric = atlasQuery()->options('numeric');

    expect($numeric)->toHaveCount(249)
        ->and($numeric)->not->toHaveKey('')
        ->and(atlasQuery()->options('iso2'))->toHaveKey('XK');
});

it('labels options by any name form', function (): void {
    expect(atlasQuery()->options('iso2', 'officialName')['KE'])->toBe('Republic of Kenya');
});

it('groups by continent including the empty groups', function (): void {
    // A grouped select box with a heading missing reads worse than one with an
    // empty group, and the caller can drop empties itself.
    $grouped = atlasQuery()->inContinent(Continent::Africa)->groupedByContinent();

    expect(array_keys($grouped))->toHaveCount(7)
        ->and($grouped['AF'])->toHaveCount(58)
        ->and($grouped['EU'])->toBe([]);
});

it('lists the distinct regions and subregions present', function (): void {
    expect(atlasQuery()->regions())->toBe(['Africa', 'Americas', 'Asia', 'Europe', 'Oceania'])
        ->and(atlasQuery()->inContinent(Continent::Africa)->regions())->toBe(['Africa']);
});

it('derives currencies from the countries so it can never list an unused one', function (): void {
    $all = atlasQuery()->currencies();
    $african = atlasQuery()->inContinent(Continent::Africa)->currencies();

    expect($all)->toContain('KES', 'USD', 'EUR')
        ->and($african)->toContain('KES')
        ->and($african)->not->toContain('JPY')
        ->and(count($african))->toBeLessThan(count($all));
});
