<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Atlas\Services;

use Closure;
use Illuminate\Contracts\Container\Container;
use Rinvex\Country\CountryLoader;
use Simtabi\Laranail\Atlas\Adapters\Generated\GeneratedPlaceRepository;
use Simtabi\Laranail\Atlas\Adapters\Rinvex\RinvexPlaceRepository;
use Simtabi\Laranail\Atlas\Core\Contracts\PlaceRepository;
use Simtabi\Laranail\Atlas\Core\Exception\UnsupportedProvider;
use Simtabi\Laranail\Atlas\Enums\Provider;
use Simtabi\Laranail\Atlas\Support\AtlasConfig;

/**
 * Resolves the configured data source, and nothing else.
 *
 * **Deliberately not `Illuminate\Support\Manager`.** A `Manager` resolves the
 * driver named `foo` by calling `$this->createFooDriver()` — it interpolates a
 * config value into a method name. That value comes from a file an operator
 * edits, and in a multi-tenant install from a database row. The enum below is
 * the allow-list: a name that is not a case never reaches a factory, so there
 * is no path from config to arbitrary dispatch.
 *
 * `extend()` takes a **closure, not a class name**, for the same reason.
 * Registering a source is then a deliberate act in application code that a
 * config edit cannot reach.
 */
final class AtlasManager
{
    /**
     * Custom sources registered at runtime.
     *
     * @var array<string, Closure(Container): PlaceRepository>
     */
    private array $custom = [];

    /** @var array<string, PlaceRepository> */
    private array $resolved = [];

    public function __construct(
        private readonly Container $container,
        private readonly AtlasConfig $config,
    ) {}

    /**
     * Register a data source.
     *
     * The closure receives the container and returns a `PlaceRepository`. It is
     * called at most once per name and the result is memoised, so an expensive
     * source is built lazily and only if something asks for it.
     *
     * A custom name shadows a built-in one of the same name, which is how a
     * consumer replaces the shipped dataset without forking the package.
     *
     * @param Closure(Container): PlaceRepository $factory
     */
    public function extend(string $name, Closure $factory): self
    {
        $this->custom[$name] = $factory;
        unset($this->resolved[$name]);

        return $this;
    }

    /**
     * The repository for the configured source.
     */
    public function repository(?string $name = null): PlaceRepository
    {
        $name ??= $this->config->string('provider', Provider::Generated->value);

        return $this->resolved[$name] ??= $this->build($name);
    }

    /**
     * Every source name that would resolve right now — built-ins plus whatever
     * has been registered. What `doctor` lists, and what an error message names.
     *
     * @return list<string>
     */
    public function available(): array
    {
        $builtIn = array_map(static fn (Provider $p): string => $p->value, Provider::cases());

        return array_values(array_unique([...$builtIn, ...array_keys($this->custom)]));
    }

    private function build(string $name): PlaceRepository
    {
        // Custom first: a consumer's registration is meant to win over a
        // built-in of the same name.
        if (isset($this->custom[$name])) {
            return ($this->custom[$name])($this->container);
        }

        $provider = Provider::tryFrom($name);

        if ($provider === null) {
            throw UnsupportedProvider::unknown($name, $this->available());
        }

        return match ($provider) {
            Provider::Generated => $this->container->make(GeneratedPlaceRepository::class),
            Provider::Rinvex => $this->buildRinvex(),
            Provider::Remote => throw UnsupportedProvider::notImplemented($provider->value),
        };
    }

    private function buildRinvex(): PlaceRepository
    {
        // Checked here rather than inside the adapter so the failure names the
        // package to install instead of surfacing as a class-not-found from
        // three frames deeper.
        if (! class_exists(CountryLoader::class)) {
            throw UnsupportedProvider::missingPackage(Provider::Rinvex->value, 'rinvex/countries');
        }

        return $this->container->make(RinvexPlaceRepository::class);
    }
}
