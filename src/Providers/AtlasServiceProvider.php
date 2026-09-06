<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Atlas\Providers;

use Override;
use Illuminate\Support\Facades\Route;
use Simtabi\Laranail\Package\Tools\Package;
use Simtabi\Laranail\Atlas\Core\Geo\Vincenty;
use Simtabi\Laranail\Atlas\Core\Geo\Haversine;
use Simtabi\Laranail\Atlas\Support\AtlasConfig;
use Illuminate\Contracts\Foundation\Application;
use Simtabi\Laranail\Atlas\Console\DoctorCommand;
use Simtabi\Laranail\Atlas\Services\AtlasManager;
use Simtabi\Laranail\Atlas\Services\AtlasService;
use Simtabi\Laranail\Atlas\Services\LocaleRegistry;
use Simtabi\Laranail\Atlas\Bridges\Chrono\ChronoBridge;
use Simtabi\Laranail\Atlas\Core\Contracts\PlaceRepository;
use Simtabi\Laranail\Atlas\Core\Contracts\IpCountryResolver;
use Simtabi\Laranail\Atlas\Core\Contracts\DistanceCalculator;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Simtabi\Laranail\Package\Tools\Providers\PackageServiceProvider;
use Simtabi\Laranail\Atlas\Adapters\Generated\GeneratedPlaceRepository;
use Simtabi\Laranail\Atlas\Adapters\Generated\GeneratedIpCountryResolver;

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
            ->hasTranslations()
            ->hasCommand(DoctorCommand::class);
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
        // Resolved out here: the closure is static, so it has no $this to call packagePath() on.
        // Arrow functions capture by value, so the resolved path travels into it.
        $dataPath = $this->packagePath('resources/data');

        $this->app->bind(
            GeneratedPlaceRepository::class,
            static fn (): GeneratedPlaceRepository => new GeneratedPlaceRepository($dataPath),
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
                default    => new Haversine,
            },
        );

        $this->app->singleton(
            IpCountryResolver::class,
            static fn (Application $app): IpCountryResolver => new GeneratedIpCountryResolver(
                $app->make(AtlasConfig::class)->nullableString('ip.table') ?? $dataPath,
            ),
        );

        $this->app->singleton(
            AtlasService::class,
            static fn (Application $app): AtlasService => new AtlasService(
                $app->make(PlaceRepository::class),
                $app->make(DistanceCalculator::class),
                $app->make(IpCountryResolver::class),
            ),
        );

        $this->app->singleton(
            ChronoBridge::class,
            static fn (Application $app): ChronoBridge => new ChronoBridge(
                $app->make(AtlasConfig::class)->bool('chrono.enabled', true),
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

    #[Override]
    public function packageBooted(): void
    {
        $this->registerApiRoutes();
    }

    /**
     * Register the API only when it is switched on.
     *
     * **Off means absent, not registered-then-blocked.** A disabled API that
     * still appears in `route:list` is one loosened middleware group away from
     * being live, and nobody reviewing that change would think to look here.
     *
     * The default in `config/atlas.php` is off, so a `composer require` adds no
     * routes to an application that only wanted the query API.
     */
    private function registerApiRoutes(): void
    {
        $config = $this->app->make(AtlasConfig::class);

        if (! $config->bool('api.enabled', false)) {
            return;
        }

        $middleware = array_values(array_filter(
            $config->array('api.middleware', ['api']),
            is_string(...),
        ));

        Route::group([
            'prefix' => trim($config->string('api.prefix', 'api/atlas'), '/')
                . '/' . trim($config->string('api.version', 'v1'), '/'),
            'middleware' => $middleware,
        ], function (): void {
            $this->loadRoutesFrom($this->packagePath('routes/api.php'));
        });
    }
}
