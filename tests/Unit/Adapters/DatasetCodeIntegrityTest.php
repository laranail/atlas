<?php

declare(strict_types=1);

use Simtabi\Laranail\Atlas\Adapters\Generated\GeneratedPlaceRepository;

/**
 * The generated dataset's codes are the keys everything else is indexed by, so
 * a bad one is not a cosmetic problem — it is a country nothing can look up.
 */
function codeTable(): array
{
    return new GeneratedPlaceRepository(dirname(__DIR__, 3).'/resources/data')->all();
}

it('has no alpha-2 code ICU does not recognise', function (): void {
    // The check that catches a code which looks plausible and is not. A sibling
    // package's postal table carried KV for Kosovo — never assigned, the
    // user-assigned code is XK — plus XY and ZU, and none of them is
    // distinguishable from a real code by shape alone. ICU echoes the input
    // back for a region it does not know, which is the tell.
    $unrecognised = [];

    foreach (array_keys(codeTable()) as $iso2) {
        $display = Locale::getDisplayRegion('-'.$iso2, 'en');

        if ($display === $iso2 || $display === '') {
            $unrecognised[] = $iso2;
        }
    }

    expect($unrecognised)->toBe([]);
})->skip(! extension_loaded('intl'), 'ext-intl is optional for this package.');

it('has a well-formed alpha-3 for every country, none duplicated', function (): void {
    $seen = [];

    foreach (codeTable() as $iso2 => $country) {
        expect($country->iso3)->toMatch('/^[A-Z]{3}$/', "{$iso2} has a malformed alpha-3.");
        expect($seen)->not->toHaveKey($country->iso3);

        $seen[$country->iso3] = $iso2;
    }

    expect($seen)->toHaveCount(count(codeTable()));
});

it('leaves the numeric code empty rather than inventing one', function (): void {
    // Kosovo has no assigned ISO numeric code. An earlier generator ran the
    // absent value through str_pad(), which produced '000' — a code that is
    // well-formed, wrong, and indistinguishable from data.
    $kosovo = codeTable()['XK'] ?? null;

    expect($kosovo)->not->toBeNull()
        ->and($kosovo->iso3)->toBe('UNK')
        ->and($kosovo->numeric)->toBe('');

    foreach (codeTable() as $iso2 => $country) {
        expect($country->numeric)->not->toBe('000', "{$iso2} carries the str_pad artefact.");

        if ($country->numeric !== '') {
            expect($country->numeric)->toMatch('/^[0-9]{3}$/', "{$iso2} has a malformed numeric code.");
        }
    }
});
