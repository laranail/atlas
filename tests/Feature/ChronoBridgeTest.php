<?php

declare(strict_types=1);

use Simtabi\Laranail\Atlas\Bridges\Chrono\ChronoBridge;
use Simtabi\Laranail\Atlas\Bridges\Chrono\ChronoBridgeUnavailable;
use Simtabi\Laranail\Atlas\Enums\Country;

/**
 * `laranail/chrono` is a `suggest`, not a dependency: its PHP floor is `^8.5`
 * and this package supports `^8.4.1`, so requiring it would drag every consumer
 * up to gain timezone lookups most never ask for.
 *
 * These assertions therefore have to hold **both** ways round — with chrono
 * present and with it absent — and the absent case is the one that ships to most
 * people, so it is the one asserted unconditionally.
 */
$chronoInstalled = class_exists('Simtabi\\Laranail\\Chrono\\Facades\\Timezones');

it('resolves from the container', function (): void {
    expect(app(ChronoBridge::class))->toBeInstanceOf(ChronoBridge::class);
});

it('reports availability honestly', function () use ($chronoInstalled): void {
    expect(app(ChronoBridge::class)->isAvailable())->toBe($chronoInstalled);
});

it('throws a message naming the package rather than a class-not-found', function (): void {
    // The failure a consumer without chrono actually meets. A `Class
    // "…\Timezones" not found` three frames deeper tells them nothing about
    // what to install, and a silent null would read as "this country has no
    // timezones".
    expect(fn () => app(ChronoBridge::class)->timezonesFor(Country::Kenya))
        ->toThrow(ChronoBridgeUnavailable::class, 'composer require laranail/chrono');
})->skip($chronoInstalled, 'chrono is installed, so this failure cannot occur here.');

it('distinguishes not-installed from switched-off', function (): void {
    // Two different fixes. A single "not available" would send someone to
    // install what they already have.
    $disabled = new ChronoBridge(enabled: false);

    expect($disabled->isAvailable())->toBeFalse()
        ->and(fn (): array => $disabled->timezonesFor(Country::Kenya))
        ->toThrow(ChronoBridgeUnavailable::class, 'laranail.atlas.chrono.enabled');
})->skip(! $chronoInstalled, 'The disabled message only applies when chrono is present.');

it('is switched off by config even when chrono is installed', function (): void {
    config()->set('laranail.atlas.chrono.enabled', false);
    app()->forgetInstance(ChronoBridge::class);

    expect(app(ChronoBridge::class)->isAvailable())->toBeFalse();
});

it('answers country to timezone when chrono is present', function (): void {
    $zones = app(ChronoBridge::class)->timezonesFor(Country::Kenya);

    expect($zones)->toContain('Africa/Nairobi')
        ->and(app(ChronoBridge::class)->primaryTimezoneFor('KE'))->toBeString();
})->skip(! $chronoInstalled, 'chrono is not installed.');

it('takes the enum or a raw code', function (): void {
    $bridge = app(ChronoBridge::class);

    expect($bridge->timezonesFor(Country::Kenya))->toBe($bridge->timezonesFor('ke'));
})->skip(! $chronoInstalled, 'chrono is not installed.');

it('names chrono in exactly one file', function (): void {
    // The property that makes the dependency genuinely optional. If a second
    // file references it, an application without chrono breaks somewhere this
    // bridge cannot guard.
    $offenders = [];

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(dirname(__DIR__, 2).'/src', FilesystemIterator::SKIP_DOTS),
    );

    foreach ($files as $file) {
        if (! $file instanceof SplFileInfo || $file->getExtension() !== 'php') {
            continue;
        }

        $path = (string) $file->getRealPath();

        if (str_contains($path, '/Bridges/Chrono/')) {
            continue;
        }

        if (str_contains((string) file_get_contents($path), 'Laranail\\Chrono')) {
            $offenders[] = basename($path);
        }
    }

    expect($offenders)->toBe([]);
});
