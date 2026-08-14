<?php

declare(strict_types=1);

use Rinvex\Country\CountryLoader;
use Simtabi\Laranail\Atlas\Adapters\Generated\GeneratedPlaceRepository;
use Simtabi\Laranail\Atlas\Core\Contracts\PlaceRepository;
use Simtabi\Laranail\Atlas\Core\Exception\UnsupportedProvider;
use Simtabi\Laranail\Atlas\Enums\Provider;
use Simtabi\Laranail\Atlas\Facades\Atlas;
use Simtabi\Laranail\Atlas\Services\AtlasManager;
use Simtabi\Laranail\Atlas\Support\AtlasConfig;

it('publishes config to the vendor-namespaced key', function (): void {
    // The laranail convention, and the reason this package does nothing to get
    // it: PackageServiceProvider namespaces by default, so ->name('laranail/atlas')
    // yields config('laranail.atlas.*') from a file at config/laranail/atlas.php.
    expect(config('laranail.atlas.provider'))->toBe(Provider::Generated->value)
        ->and(config('atlas.provider'))->toBeNull();
});

it('reads config through one prefixed accessor', function (): void {
    $config = app(AtlasConfig::class);

    expect($config->string('provider'))->toBe('generated')
        ->and($config->int('cache.ttl'))->toBe(1440)
        ->and($config->bool('api.enabled'))->toBeFalse()
        ->and($config->nullableString('cache.store'))->toBeNull();
});

it('resolves the repository seam rather than a concrete adapter', function (): void {
    // Consumers type-hint the interface, so a test can swap the entire data
    // source with one instance() call and nothing above notices.
    expect(app(PlaceRepository::class))->toBeInstanceOf(GeneratedPlaceRepository::class);
});

it('answers a country lookup through the container', function (): void {
    expect(app(PlaceRepository::class)->find('KE')?->name)->toBe('Kenya');
});

it('lists the sources that would resolve', function (): void {
    // Through the container, not the facade: AtlasManager is driver plumbing
    // and the Atlas facade points at the query API instead.
    expect(app(AtlasManager::class)->available())->toContain('generated', 'rinvex', 'remote');
});

it('answers country questions through the facade', function (): void {
    expect(Atlas::country('KE')?->name)->toBe('Kenya')
        ->and(Atlas::options())->toHaveKey('KE')
        ->and(Atlas::continents())->toHaveCount(7)
        ->and(Atlas::describe()['countries'])->toBe(250);
});

it('refuses a provider name that is not in the allow-list', function (): void {
    // The enum is the gate. A config value can never become a class name or a
    // method name — which is why this is not Illuminate\Support\Manager, whose
    // whole resolution strategy is interpolating that value into a method call.
    expect(fn () => app(AtlasManager::class)->repository('createFooDriver'))
        ->toThrow(UnsupportedProvider::class, 'Unknown atlas provider');
});

it('names the package to install for a source whose data is missing', function (): void {
    expect(fn () => app(AtlasManager::class)->repository('rinvex'))
        ->toThrow(UnsupportedProvider::class, 'rinvex/countries');
})->skip(
    class_exists(CountryLoader::class),
    'rinvex/countries is installed, so this failure cannot occur here.',
);

it('refuses the unimplemented source instead of falling back', function (): void {
    // Falling back would answer with data the operator did not choose.
    expect(fn () => app(AtlasManager::class)->repository('remote'))
        ->toThrow(UnsupportedProvider::class, 'not implemented');
});

/**
 * A source that is not the shipped one. Implements the seam rather than
 * extending the adapter, which is `final` — a consumer swapping the data source
 * satisfies the interface; it does not subclass someone else's loader.
 */
function atlasStub(string $version): PlaceRepository
{
    return new readonly class($version) implements PlaceRepository
    {
        public function __construct(private string $version) {}

        public function all(): array
        {
            return [];
        }

        public function find(string $code): null
        {
            return null;
        }

        public function isAvailable(): bool
        {
            return true;
        }

        public function version(): string
        {
            return $this->version;
        }
    };
}

it('takes a closure to extend, never a class name', function (): void {
    app(AtlasManager::class)->extend('stub', fn (): PlaceRepository => atlasStub('stub'));

    expect(app(AtlasManager::class)->repository('stub')->version())->toBe('stub')
        ->and(app(AtlasManager::class)->available())->toContain('stub');
});

it('lets a registered source shadow a built-in of the same name', function (): void {
    // How a consumer replaces the shipped dataset without forking the package.
    app(AtlasManager::class)->extend('generated', fn (): PlaceRepository => atlasStub('shadowed'));

    expect(app(AtlasManager::class)->repository('generated')->version())->toBe('shadowed');
});

it('registers no routes while the api is disabled', function (): void {
    // Off means absent, not registered-then-blocked: there is nothing to expose
    // by loosening middleware later.
    $atlasRoutes = collect(app('router')->getRoutes()->getRoutes())
        ->filter(fn ($route): bool => str_contains((string) $route->uri(), 'atlas'));

    expect(config('laranail.atlas.api.enabled'))->toBeFalse()
        ->and($atlasRoutes)->toBeEmpty();
});
