<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Atlas\Services;

use Simtabi\Laranail\Atlas\Core\Contracts\DistanceCalculator;
use Simtabi\Laranail\Atlas\Core\Contracts\IpCountryResolver;
use Simtabi\Laranail\Atlas\Core\Contracts\PlaceRepository;
use Simtabi\Laranail\Atlas\Core\Country\CountryQuery;
use Simtabi\Laranail\Atlas\Core\Country\CountryRecord;
use Simtabi\Laranail\Atlas\Core\Geo\Coordinates;
use Simtabi\Laranail\Atlas\Core\Geo\Distance;
use Simtabi\Laranail\Atlas\Core\Network\IpAddress;
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
        private DistanceCalculator $distances,
        private IpCountryResolver $ips,
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
     * How far apart two points are, by the configured formula.
     *
     * Returns a {@see Distance}, not a float. The helper this replaces returned
     * a bare number whose unit was decided by a string argument several lines
     * earlier, so `$d > 100` could not be read without scrolling and changing
     * the unit at the call site silently rescaled every comparison below it.
     */
    public function distance(Coordinates $from, Coordinates $to): Distance
    {
        return $this->distances->between($from, $to);
    }

    /**
     * Between two countries' centroids, or null if either is unknown or has no
     * coordinates.
     *
     * A centroid is not a city and not a border. This answers "roughly how far
     * apart are these two countries", which is a real question, and not "how far
     * is the journey", which it cannot answer.
     */
    public function distanceBetween(Country|string $from, Country|string $to): ?Distance
    {
        $a = $this->country($from)?->coordinates;
        $b = $this->country($to)?->coordinates;

        if (! $a instanceof Coordinates || ! $b instanceof Coordinates) {
            return null;
        }

        return $this->distance($a, $b);
    }

    /**
     * The country an IP address was allocated to, or null.
     *
     * Offline, over registry delegation data — no network call and no API key.
     * Country and nothing else: city, ISP and VPN status are not in that data
     * and cannot be derived from it. `laranail/ip-intel` is where those live.
     *
     * Null covers a reserved address, a registry gap, and an uninstalled
     * dataset. `describe()['ip_ready']` separates the last from the first two,
     * because one is a deployment problem and the others are just how the
     * internet is.
     */
    public function countryForIp(IpAddress|string $address): ?CountryRecord
    {
        $ip = $address instanceof IpAddress ? $address : IpAddress::parse($address);

        if (! $ip instanceof IpAddress) {
            return null;
        }

        $code = $this->ips->countryFor($ip);

        return $code === null ? null : $this->country($code);
    }

    /**
     * Which data source answered, and what version of it.
     *
     * @return array{provider: string, version: ?string, available: bool, countries: int, distance: string, ip_ready: bool}
     */
    public function describe(): array
    {
        return [
            'provider' => $this->repository::class,
            'version' => $this->repository->version(),
            'available' => $this->repository->isAvailable(),
            'countries' => count($this->repository->all()),
            'distance' => $this->distances->name(),
            'ip_ready' => $this->ips->isReady(),
        ];
    }
}
