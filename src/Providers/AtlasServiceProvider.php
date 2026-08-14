<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Atlas\Providers;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Foundation\Application;
use Override;
use Simtabi\Laranail\Atlas\Adapters\Generated\GeneratedPlaceRepository;
use Simtabi\Laranail\Atlas\Core\Contracts\DistanceCalculator;
use Simtabi\Laranail\Atlas\Core\Contracts\PlaceRepository;
use Simtabi\Laranail\Atlas\Core\Geo\Haversine;
use Simtabi\Laranail\Atlas\Core\Geo\Vincenty;
use Simtabi\Laranail\Atlas\Services\AtlasManager;
use Simtabi\Laranail\Atlas\Services\AtlasService;
use Simtabi\Laranail\Atlas\Services\LocaleRegistry;
use Simtabi\Laranail\Atlas\Support\AtlasConfig;
use Simtabi\Laranail\Package\Tools\Package;
use Simtabi\Laranail\Package\Tools\Providers\PackageServiceProvider;

/**
 * Entry point for laranail/atlas.
 *
 * Configuration is vendor-namespaced, which `PackageServiceProvider` does by
 * default: the file publishes to `config/laranail/atlas.php` and application
 * code reads `config('laranail.atlas.*')`, matching the `laranail::atlas.<command>`
 * shape commands use. Publish tags are `laranail::atlas-*`.
 *
 * @internal Auto-discovered framework wiring; not part of the public API.
 */
final class AtlasServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laranail/atlas')
            ->setPublishTagId('atlas')
            ->hasConfigFile('atlas')
            ->hasTranslations('atlas');
    }

    #[Override]
    public function packageRegistered(): void
    {
        $this->app->singleton(
            AtlasConfig::class,
            static fn (Application $app): AtlasConfig => new AtlasConfig($app->make(ConfigRepository::class)),
        );

        $this->app->singleton(
            AtlasManager::class,
            static fn (Application $app): AtlasManager => new AtlasManager(
                $app,
                $app->make(AtlasConfig::class),
            ),
        );

        // Bound to the interface, not the concrete adapter, so a consumer that
        // type-hints PlaceRepository gets whatever the config selected — and so
        // a test can swap the whole data source with one instance() call.
        $this->app->bind(
            PlaceRepository::class,
            static fn (Application $app): PlaceRepository => $app->make(AtlasManager::class)->repository(),
        );

        // The default source, registered here rather than inside the manager so
        // the manager holds no knowledge of any concrete adapter.
        $this->app->bind(
            GeneratedPlaceRepository::class,
            static fn (): GeneratedPlaceRepository => new GeneratedPlaceRepository(
                dirname(__DIR__, 2) . '/resources/data',
            ),
        );

        // Resolved through a match on the enum, not by interpolating the config
        // value into a class name. An unrecognised formula falls back to the
        // spherical one and says so in `describe()`, rather than throwing at the
        // first distance calculation of a request — a mistyped formula is a
        // config error, not a reason to break a page.
        $this->app->singleton(
            DistanceCalculator::class,
            static fn (Application $app): DistanceCalculator => match (
                strtolower($app->make(AtlasConfig::class)->string('distance.formula', 'haversine'))
            ) {
                'vincenty' => new Vincenty,
                default => new Haversine,
            },
        );

        $this->app->singleton(
            AtlasService::class,
            static fn (Application $app): AtlasService => new AtlasService(
                $app->make(PlaceRepository::class),
                $app->make(DistanceCalculator::class),
            ),
        );

        $this->app->singleton(
            LocaleRegistry::class,
            static fn (Application $app): LocaleRegistry => new LocaleRegistry(
                // lang_path() first: Laravel moved this directory to the project
                // root in version 9, and the module this package replaces was
                // still scanning resources/lang — so availableLocales() returned
                // an empty list on every modern application. resources/lang is
                // kept as a second look for projects that upgraded without
                // moving it.
                [
                    $app->langPath(),
                    $app->resourcePath('lang'),
                ],
                $app->make(PlaceRepository::class),
            ),
        );
    }
}
