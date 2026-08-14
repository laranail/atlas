<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Atlas\Facades;

use Illuminate\Support\Facades\Facade;
use Simtabi\Laranail\Atlas\Core\Country\CountryQuery;
use Simtabi\Laranail\Atlas\Core\Country\CountryRecord;
use Simtabi\Laranail\Atlas\Core\Country\PhoneRules;
use Simtabi\Laranail\Atlas\Core\Geo\Coordinates;
use Simtabi\Laranail\Atlas\Core\Region\Continent;
use Simtabi\Laranail\Atlas\Services\AtlasManager;
use Simtabi\Laranail\Atlas\Services\AtlasService;

/**
 * Points at {@see AtlasService} — the query API — and not at
 * {@see AtlasManager}, which is driver
 * plumbing. Registering a data source is a one-line act in a provider and does
 * not need a facade; asking which countries use the euro happens everywhere.
 *
 * @method static CountryQuery query()
 * @method static CountryRecord|null country(string $code)
 * @method static CountryRecord countryOrFail(string $code)
 * @method static CountryRecord|null countryByName(string $name)
 * @method static CountryRecord|null countryByDialCode(string $dialCode)
 * @method static PhoneRules|null phoneRules(string $code)
 * @method static list<CountryRecord> countries()
 * @method static array<string, string> options(string $key = 'iso2', string $label = 'name')
 * @method static list<CountryRecord> inContinent(Continent|string $continent)
 * @method static array<string, list<CountryRecord>> groupedByContinent()
 * @method static Continent|null continentFor(string $code)
 * @method static array<string, string> continents()
 * @method static list<string> regions()
 * @method static list<string> subregions()
 * @method static list<string> currencies()
 * @method static list<string> languages()
 * @method static list<CountryRecord> at(Coordinates $point)
 * @method static array{provider: string, version: ?string, available: bool, countries: int} describe()
 *
 * @see AtlasService
 */
final class Atlas extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return AtlasService::class;
    }
}
