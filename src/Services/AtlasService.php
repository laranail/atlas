<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Atlas\Services;

use Simtabi\Laranail\Atlas\Core\Contracts\PlaceRepository;
use Simtabi\Laranail\Atlas\Core\Country\CountryQuery;
use Simtabi\Laranail\Atlas\Core\Country\CountryRecord;
use Simtabi\Laranail\Atlas\Core\Geo\Coordinates;
use Simtabi\Laranail\Atlas\Core\Region\Continent;
use Simtabi\Laranail\Atlas\Enums\Country;

/**
 * The package's entry point.
 *
 * Thin by design: `query()` returns the builder and everything else is a named
 * shortcut for a common chain. The old module put fourteen fixed methods on one
 * class and left anything it had not anticipated to be filtered by hand at the
 * call site; here the shortcuts are conveniences over a composable builder
 * rather than the only way in.
 */
final readonly class AtlasService
{
    public function __construct(
        private PlaceRepository $repository,
    ) {}

    /**
     * A fresh query. Everything else on this class is one of these with a name.
     */
    public function query(): CountryQuery
    {
        return CountryQuery::over($this->repository);
    }

    /**
     * One country by enum case, or by ISO alpha-2, alpha-3 or numeric code.
     *
     * The enum is accepted everywhere a code is, so a call site can be as
     * typed or as loose as its input allows — `Country::KE` where the country
     * is known at authoring time, a raw string where it came from a request.
     */
    public function country(Country|string $code): ?CountryRecord
    {
        return $this->repository->find($code instanceof Country ? $code->value : $code);
    }

    /**
     * One country, or throw.
     */
    public function countryOrFail(Country|string $code): CountryRecord
    {
        return $this->query()->findOrFail($code instanceof Country ? $code->value : $code);
    }

    /**
     * Every country, sorted by name.
     *
     * @return list<CountryRecord>
     */
    public function countries(): array
    {
        return $this->query()->sortedByName()->get();
    }

    /**
     * A `code => label` map for a select box.
     *
     * @param 'iso2'|'iso3'|'numeric' $key
     * @param 'name'|'officialName'|'nativeName' $label
     * @return array<string, string>
     */
    public function options(string $key = 'iso2', string $label = 'name'): array
    {
        return $this->query()->options($key, $label);
    }

    /**
     * @return list<CountryRecord>
     */
    public function inContinent(Continent|string $continent): array
    {
        return $this->query()->inContinent($continent)->sortedByName()->get();
    }

    /**
     * @return array<string, list<CountryRecord>>
     */
    public function groupedByContinent(): array
    {
        return $this->query()->groupedByContinent();
    }

    public function continentFor(string $code): ?Continent
    {
        $country = $this->country($code);

        return ! $country instanceof CountryRecord ? null : Continent::tryFrom($country->continent);
    }

    /**
     * The continents, as `code => label`.
     *
     * From the enum rather than from config. The old module read a
     * `laranail.toolkit.atlas.continents` map, so an application that published
     * the config and trimmed the list got a `countriesByContinent()` that
     * silently dropped every country on a removed continent — a display
     * preference quietly deleting data.
     *
     * @return array<string, string>
     */
    public function continents(): array
    {
        return Continent::options();
    }

    /**
     * @return list<string>
     */
    public function regions(): array
    {
        return $this->query()->regions();
    }

    /**
     * @return list<string>
     */
    public function subregions(): array
    {
        return $this->query()->subregions();
    }

    /**
     * Every ISO 4217 code in use, derived from the countries themselves.
     *
     * @return list<string>
     */
    public function currencies(): array
    {
        return $this->query()->currencies();
    }

    /**
     * Every ISO 639 code in use.
     *
     * @return list<string>
     */
    public function languages(): array
    {
        return $this->query()->languages();
    }

    /**
     * The countries whose bounding box contains a point.
     *
     * A box is not a border, so this can return more than one. It is a cheap
     * pre-filter for a geocoder, not a replacement for one.
     *
     * @return list<CountryRecord>
     */
    public function at(Coordinates $point): array
    {
        return $this->query()->containing($point)->sortedByName()->get();
    }

    /**
     * Which data source answered, and what version of it.
     *
     * @return array{provider: string, version: ?string, available: bool, countries: int}
     */
    public function describe(): array
    {
        return [
            'provider' => $this->repository::class,
            'version' => $this->repository->version(),
            'available' => $this->repository->isAvailable(),
            'countries' => count($this->repository->all()),
        ];
    }
}
