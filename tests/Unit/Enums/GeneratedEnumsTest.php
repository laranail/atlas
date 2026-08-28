<?php

declare(strict_types=1);

use Simtabi\Laranail\Atlas\Enums\Country;
use Simtabi\Laranail\Atlas\Enums\Currency;
use Simtabi\Laranail\Atlas\Enums\Language;
use Simtabi\Laranail\Atlas\Adapters\Generated\GeneratedPlaceRepository;

/**
 * The enums are generated from the dataset, so the only thing worth asserting
 * is that they still *are* — that no case drifted from the data it mirrors, and
 * that regenerating on another machine produces the same bytes.
 *
 * That last point is why case names never go through
 * `iconv('UTF-8', 'ASCII//TRANSLIT', …)`: glibc and BSD disagree. Measured on
 * macOS, `São Tomé and Príncipe` transliterates to `S~ao Tom'e and Pr'incipe`,
 * whose case name is `SAoTomEAndPrIncipe` rather than `SaoTomeAndPrincipe` — so
 * the `--check` gate would fail for whoever regenerated on the other platform.
 */
function atlasData(): array
{
    return new GeneratedPlaceRepository(dirname(__DIR__, 3) . '/resources/data')->all();
}

it('has one country case per country in the dataset', function (): void {
    expect(Country::cases())->toHaveCount(count(atlasData()));
});

it('backs every country case with a code the dataset knows', function (): void {
    $data = atlasData();
    $unknown = [];

    foreach (Country::cases() as $case) {
        if (! isset($data[$case->value])) {
            $unknown[] = $case->name . ' = ' . $case->value;
        }
    }

    expect($unknown)->toBe([]);
});

it('has a case for every country in the dataset', function (): void {
    $values = array_column(Country::cases(), 'value');
    $missing = array_values(array_diff(array_keys(atlasData()), $values));

    expect($missing)->toBe([]);
});

it('names accented countries without their accents', function (string $case, string $code): void {
    // The transliteration table, exercised through the generator's output.
    expect(Country::{$case}->value)->toBe($code);
})->with([
    ['AlandIslands', 'AX'],
    ['Curacao', 'CW'],
    ['Reunion', 'RE'],
    ['SaoTomeAndPrincipe', 'ST'],
]);

it('resolves a country case from a code', function (): void {
    expect(Country::tryFrom('KE'))->toBe(Country::Kenya)
        ->and(Country::tryFrom('ZZ'))->toBeNull();
});

it('has one currency case per currency in use', function (): void {
    $inUse = [];

    foreach (atlasData() as $country) {
        foreach ($country->currencies as $code) {
            $inUse[$code] = true;
        }
    }

    expect(Currency::cases())->toHaveCount(count($inUse));
});

it('lists no currency that no country uses', function (): void {
    // Derived from the countries rather than from a separate register, which is
    // what makes that impossible rather than merely unlikely.
    $inUse = [];

    foreach (atlasData() as $country) {
        foreach ($country->currencies as $code) {
            $inUse[$code] = true;
        }
    }

    $orphans = array_values(array_diff(array_column(Currency::cases(), 'value'), array_keys($inUse)));

    expect($orphans)->toBe([]);
});

it('keeps language codes lower case in the value and upper case in the name', function (): void {
    // A PHP case name is case-sensitive, so `swa` beside `SWA` would read as two
    // languages. The canonical lower-case form stays in the backing value.
    expect(Language::SWA->value)->toBe('swa')
        ->and(Language::ENG->value)->toBe('eng');
});

it('is still what the generator produces', function (): void {
    // Catches a hand edit, which the assertions above cannot see: adding a
    // method or reformatting leaves every case intact and still puts the file
    // and its generator permanently at odds.
    $generator = dirname(__DIR__, 3) . '/tools/generate-enums.php';

    $output = [];
    $exitCode = 0;
    exec(sprintf('%s %s --check 2>&1', escapeshellarg(PHP_BINARY), escapeshellarg($generator)), $output, $exitCode);

    expect($exitCode)->toBe(0, implode("\n", $output));
});
