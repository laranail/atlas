<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Atlas\Services;

use Simtabi\Laranail\Atlas\Enums\Country;
use Simtabi\Laranail\Atlas\Core\Geo\Distance;
use Simtabi\Laranail\Atlas\Core\Geo\Coordinates;
use Simtabi\Laranail\Atlas\Core\Country\FormData;
use Simtabi\Laranail\Atlas\Core\Region\Continent;
use Simtabi\Laranail\Atlas\Core\Network\IpAddress;
use Simtabi\Laranail\Atlas\Core\Country\PhoneRules;
use Simtabi\Laranail\Atlas\Core\Country\CountryQuery;
use Simtabi\Laranail\Atlas\Core\Country\CountryRecord;
use Simtabi\Laranail\Atlas\Core\Contracts\PlaceRepository;
use Simtabi\Laranail\Atlas\Core\Contracts\IpCountryResolver;
use Simtabi\Laranail\Atlas\Core\Contracts\DistanceCalculator;

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
     * typed or as loose as its input allows — `Country::Kenya` where the country
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
     * One country by name, exact and case-insensitive across all three names.
     */
    public function countryByName(string $name): ?CountryRecord
    {
        return $this->query()->findByName($name);
    }

    /**
     * One country by calling code.
     *
     * Codes are shared: +1 is the whole North American Numbering Plan. Use
     * {@see CountryQuery::allByDialCode()} when the rest matter.
     */
    public function countryByDialCode(string $dialCode): ?CountryRecord
    {
        return $this->query()->findByDialCode($dialCode);
    }

    /**
     * Phone number rules for a country code, or null when it is not a country.
     */
    public function phoneRules(Country|string $code): ?PhoneRules
    {
        return $this->query()->phoneRulesFor($code instanceof Country ? $code->value : $code);
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
     * The catalogue as a form needs it — `value => label` maps for a `<select>`.
     *
     * Everything a form asks for lives behind this one call:
     * `form()->options()`, `form()->groupedOptions()`, `form()->continents()`,
     * `form()->dialCodes()`, `form()->currencies()`, `form()->languages()`,
     * `form()->regions()`, `form()->subregions()`.
     *
     * Separate from the methods above because the shapes differ and the names
     * did not say so: `continents()` used to sit beside `regions()` returning a
     * map where the other returned a list. Now the map lives here and the list
     * stays there, and which one you are getting is legible from the call.
     *
     * Start from a query to narrow it — `Atlas::query()->inhabitedOnly()->form()`
     * gives the same maps over a filtered catalogue.
     */
    public function form(): FormData
    {
        return FormData::over($this->repository);
    }

    /**
     * @return list<CountryRecord>
     */
    public function inContinent(Continent|string $continent): array
    {
        return $this->query()->inContinent($continent)->sortedByName()->get();
    }

    /**
     * Every country, keyed by continent code.
     *
     * Records, not labels — {@see form()}`->groupedOptions()` is the `<optgroup>`
     * shape. Every continent appears, including ones with no countries left, and
     * the caller drops the empties if it prefers.
     *
     * @return array<string, list<CountryRecord>>
     */
    public function countriesGroupedByContinent(): array
    {
        return $this->query()->groupedByContinent();
    }

    public function continentFor(string $code): ?Continent
    {
        $country = $this->country($code);

        return ! $country instanceof CountryRecord ? null : Continent::tryFrom($country->continent);
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
    public function countriesAt(Coordinates $point): array
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
     * Named for its arguments rather than as a bare `distanceBetween()`, which
     * sat beside `distance()` taking coordinates and read as its overload. A
     * centroid is not a city and not a border: this answers "roughly how far
     * apart are these two countries", which is a real question, and not "how far
     * is the journey", which it cannot answer.
     */
    public function distanceBetweenCountries(Country|string $from, Country|string $to): ?Distance
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
            'provider'  => $this->repository::class,
            'version'   => $this->repository->version(),
            'available' => $this->repository->isAvailable(),
            'countries' => count($this->repository->all()),
            'distance'  => $this->distances->name(),
            'ip_ready'  => $this->ips->isReady(),
        ];
    }
}
