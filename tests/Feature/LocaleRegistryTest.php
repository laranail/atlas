<?php

declare(strict_types=1);

use Simtabi\Laranail\Atlas\Services\LocaleRegistry;

/**
 * P0-7, fixed on the way in.
 *
 * The toolkit module scanned `resource_path('lang')`. **Laravel moved that
 * directory to the project root in version 9**, so on every modern application
 * the path does not exist and `availableLocales()` returned `[]` — a language
 * switcher with nothing in it, from a package whose job is to fill one.
 *
 * It survived because the test asserting the behaviour created
 * `resource_path('lang')` itself in `setUp()` before scanning it. The scan found
 * what the test had just put there and passed against a directory no real
 * application has.
 *
 * So this file asserts the wiring — which path the container hands the registry
 * — and creates nothing. Behaviour is covered in `tests/Unit/Services`, against
 * a sandbox, so no test can arrange the world it then measures.
 */
it('is given lang_path first, where Laravel actually keeps translations', function (): void {
    $paths = new ReflectionProperty(LocaleRegistry::class, 'searchPaths')
        ->getValue(app(LocaleRegistry::class));

    expect($paths)->toBeArray()
        ->and($paths[0])->toBe(app()->langPath());
});

it('also looks in resources/lang for a project that upgraded without moving it', function (): void {
    $paths = new ReflectionProperty(LocaleRegistry::class, 'searchPaths')
        ->getValue(app(LocaleRegistry::class));

    expect($paths)->toContain(app()->resourcePath('lang'));
});

it('does not look anywhere else', function (): void {
    // A registry that scanned, say, base_path() would report every top-level
    // directory as a locale.
    $paths = new ReflectionProperty(LocaleRegistry::class, 'searchPaths')
        ->getValue(app(LocaleRegistry::class));

    expect($paths)->toHaveCount(2);
});

it('resolves from the container with the shared repository', function (): void {
    expect(app(LocaleRegistry::class))->toBeInstanceOf(LocaleRegistry::class)
        ->and(app(LocaleRegistry::class))->toBe(app(LocaleRegistry::class));
});
