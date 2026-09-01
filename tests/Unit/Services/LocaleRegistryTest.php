<?php

declare(strict_types=1);

use Simtabi\Laranail\Atlas\Adapters\Generated\GeneratedPlaceRepository;
use Simtabi\Laranail\Atlas\Services\LocaleRegistry;

/**
 * Behaviour, against a sandbox rather than the application's own directories.
 *
 * The registry takes its search paths by constructor for exactly this reason.
 * The Feature test asserts the container passes `lang_path()` first — that is
 * the P0-7 fix — and these assert what the scan does once it has a path,
 * without any test being able to disturb another.
 *
 * That separation is the lesson from the bug being fixed. The toolkit module
 * scanned `resource_path('lang')`, which Laravel abandoned in version 9, and the
 * test asserting the behaviour created that directory itself in `setUp()` before
 * scanning it — so it passed against a path no real application has.
 */
function atlasSandbox(): string
{
    $dir = sys_get_temp_dir().'/atlas-locales-'.bin2hex(random_bytes(6));
    mkdir($dir, 0o777, true);

    return $dir;
}

function atlasRegistry(string ...$paths): LocaleRegistry
{
    return new LocaleRegistry(
        array_values($paths),
        new GeneratedPlaceRepository(dirname(__DIR__, 3).'/resources/data'),
    );
}

afterEach(function (): void {
    foreach (glob(sys_get_temp_dir().'/atlas-locales-*') ?: [] as $dir) {
        exec('rm -rf '.escapeshellarg($dir));
    }
});

it('lists the locale directories it finds', function (): void {
    $sandbox = atlasSandbox();
    mkdir($sandbox.'/fr');
    mkdir($sandbox.'/en_GB');

    expect(atlasRegistry($sandbox)->installed())->toBe(['en_GB', 'fr']);
});

it('finds nothing when the path does not exist', function (): void {
    expect(atlasRegistry('/nonexistent/atlas/lang')->installed())->toBe([]);
});

it('finds nothing in an empty directory', function (): void {
    expect(atlasRegistry(atlasSandbox())->installed())->toBe([]);
});

it('ignores files, counting only directories', function (): void {
    $sandbox = atlasSandbox();
    mkdir($sandbox.'/de');
    file_put_contents($sandbox.'/en.json', '{}');

    expect(atlasRegistry($sandbox)->installed())->toBe(['de']);
});

it('skips the vendor directory', function (): void {
    // That holds published package translations, not application locales.
    $sandbox = atlasSandbox();
    mkdir($sandbox.'/vendor');
    mkdir($sandbox.'/nl');

    expect(atlasRegistry($sandbox)->installed())->toBe(['nl']);
});

it('merges several paths without duplicating', function (): void {
    $first = atlasSandbox();
    $second = atlasSandbox();
    mkdir($first.'/es');
    mkdir($second.'/es');
    mkdir($second.'/it');

    expect(atlasRegistry($first, $second)->installed())->toBe(['es', 'it']);
});

it('resolves a region subtag to its country', function (): void {
    $sandbox = atlasSandbox();
    mkdir($sandbox.'/en_GB');

    $detailed = atlasRegistry($sandbox)->detailed();

    expect($detailed['en_GB']['language'])->toBe('en')
        ->and($detailed['en_GB']['region'])->toBe('GB')
        ->and($detailed['en_GB']['country'])->toBe('United Kingdom')
        ->and($detailed['en_GB']['flag'])->toBe('🇬🇧');
});

it('accepts either locale separator', function (string $locale): void {
    // BCP 47 says en-GB; PHP and Laravel conventions say en_GB. Both turn up as
    // directory names in the wild.
    $sandbox = atlasSandbox();
    mkdir($sandbox.'/'.$locale);

    expect(atlasRegistry($sandbox)->detailed()[$locale]['country'])->toBe('Brazil');
})->with(['pt_BR', 'pt-BR']);

it('leaves the country null for a locale with no region rather than guessing', function (): void {
    // Guessing means picking one nation's flag for a language many speak, which
    // is not a choice a package should make for an application.
    $sandbox = atlasSandbox();
    mkdir($sandbox.'/ar');

    $detailed = atlasRegistry($sandbox)->detailed();

    expect($detailed['ar']['region'])->toBeNull()
        ->and($detailed['ar']['country'])->toBeNull()
        ->and($detailed['ar']['flag'])->toBeNull();
});

it('leaves the country null for a region subtag that is not a country', function (): void {
    $sandbox = atlasSandbox();
    mkdir($sandbox.'/xx_ZZ');

    expect(atlasRegistry($sandbox)->detailed()['xx_ZZ']['country'])->toBeNull();
});

it('answers has() against what is installed', function (): void {
    $sandbox = atlasSandbox();
    mkdir($sandbox.'/sw');

    expect(atlasRegistry($sandbox)->has('sw'))->toBeTrue()
        ->and(atlasRegistry($sandbox)->has('ja'))->toBeFalse();
});
