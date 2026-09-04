<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Atlas\Adapters\Rinvex;

use Throwable;
use Rinvex\Country\CountryLoader;
use Simtabi\Laranail\Atlas\Core\Geo\BoundingBox;
use Simtabi\Laranail\Atlas\Core\Geo\Coordinates;
use Simtabi\Laranail\Atlas\Core\Country\CountryRecord;
use Simtabi\Laranail\Atlas\Core\Contracts\PlaceRepository;

/**
 * Reads `rinvex/countries` live, for applications already carrying it.
 *
 * The shipped `Generated` dataset is built from the same source at build time,
 * so this exists for two cases: an application that already has the package and
 * would rather not carry a second copy of the data, and one that needs the long
 * list to move ahead of atlas's release cadence.
 *
 * Hydration is deliberately the same shape as the generator's, because the two
 * must agree — a country that reads differently through this adapter than
 * through the shipped file would make the choice of source observable, which is
 * exactly what {@see PlaceRepository} exists to prevent.
 */
final class RinvexPlaceRepository implements PlaceRepository
{
    /** @var array<string, CountryRecord>|null */
    private ?array $countries = null;

    public function all(): array
    {
        if ($this->countries !== null) {
            return $this->countries;
        }

        $countries = [];

        /** @var array<string, array<string, mixed>> $short */
        $short = CountryLoader::countries();

        foreach (array_keys($short) as $key) {
            $long = $this->longList((string) $key);

            if ($long === null) {
                continue;
            }

            $iso2 = strtoupper($this->str($long, 'iso_3166_1_alpha2'));

            if ($iso2 === '') {
                continue;
            }

            $countries[$iso2] = $this->hydrate($iso2, $long);
        }

        ksort($countries);

        return $this->countries = $countries;
    }

    public function find(string $code): ?CountryRecord
    {
        $code = strtoupper(trim($code));

        if ($code === '') {
            return null;
        }

        $countries = $this->all();

        if (strlen($code) === 2) {
            return $countries[$code] ?? null;
        }

        if (strlen($code) !== 3) {
            return null;
        }

        $numeric = ctype_digit($code);

        foreach ($countries as $country) {
            if ($numeric ? $country->numeric === $code : $country->iso3 === $code) {
                return $country;
            }
        }

        return null;
    }

    public function isAvailable(): bool
    {
        return class_exists(CountryLoader::class);
    }

    public function version(): ?string
    {
        // The loader exposes no version, and inferring one from a file mtime
        // would be a number that looks authoritative and is not. doctor reports
        // null as unknown, which is the true answer.
        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function longList(string $code): ?array
    {
        try {
            // `$hydrate = false` returns the decoded array directly. Asking for
            // the Country object would mean unwrapping it again through
            // getAttributes(), for a value object this adapter's whole job is
            // to replace with CountryRecord.
            $data = CountryLoader::country($code, false);
        } catch (Throwable) {
            // The loader throws for a code it has a short-list entry but no
            // long-list file for. Skipping is right: a record we cannot fill is
            // worse than an absent one, because a caller cannot tell the
            // difference between a missing field and a real empty.
            return null;
        }

        return is_array($data) && $data !== [] ? $data : null;
    }

    /**
     * @param array<string, mixed> $long
     */
    private function hydrate(string $iso2, array $long): CountryRecord
    {
        $name = $this->arr($long, 'name');
        $geo = $this->arr($long, 'geo');
        $dialling = $this->arr($long, 'dialling');

        $rawNumeric = trim($this->str($long, 'iso_3166_1_numeric'));

        $latitude = $this->decimal($geo['latitude_desc'] ?? null);
        $longitude = $this->decimal($geo['longitude_desc'] ?? null);

        $tld = $this->arr($long, 'tld');
        $currencies = array_keys($this->arr($long, 'currency'));
        $languages = array_keys($this->arr($long, 'languages'));
        $calling = $this->arr($dialling, 'calling_code');

        return new CountryRecord(
            iso2: $iso2,
            iso3: strtoupper($this->str($long, 'iso_3166_1_alpha3')),
            numeric: $rawNumeric === '' ? '' : str_pad($rawNumeric, 3, '0', STR_PAD_LEFT),
            name: $this->str($name, 'common'),
            officialName: $this->str($name, 'official'),
            nativeName: $this->nativeName($name) ?? $this->str($name, 'common'),
            continent: (string) (array_key_first($this->arr($geo, 'continent')) ?? ''),
            region: $this->nullableStr($geo, 'region'),
            subregion: $this->nullableStr($geo, 'subregion'),
            currencies: array_values(array_map(strtoupper(...), array_filter($currencies, is_string(...)))),
            languages: array_values(array_filter($languages, is_string(...))),
            callingCodes: array_values(array_map(strval(...), array_filter(
                $calling,
                static fn (mixed $c): bool => is_string($c) || is_int($c),
            ))),
            tld: isset($tld[0]) && is_string($tld[0]) ? $tld[0] : null,
            coordinates: $latitude !== null && $longitude !== null ? new Coordinates($latitude, $longitude) : null,
            bounds: $this->bounds($geo),
        );
    }

    /**
     * @param array<string, mixed> $name
     */
    private function nativeName(array $name): ?string
    {
        $native = $this->arr($name, 'native');

        foreach ($native as $entry) {
            if (is_array($entry) && is_string($entry['common'] ?? null) && $entry['common'] !== '') {
                return $entry['common'];
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $geo
     */
    private function bounds(array $geo): ?BoundingBox
    {
        $west = $this->decimal($geo['min_longitude'] ?? null);
        $south = $this->decimal($geo['min_latitude'] ?? null);
        $east = $this->decimal($geo['max_longitude'] ?? null);
        $north = $this->decimal($geo['max_latitude'] ?? null);

        if ($west === null || $south === null || $east === null || $north === null) {
            return null;
        }

        return BoundingBox::fromBbox($west, $south, $east, $north);
    }

    /**
     * The long list carries `latitude` as a DMS string ("1 00 N") and
     * `latitude_desc` as a decimal *string*. A bare cast on the wrong field
     * silently yields 1.0 for Kenya instead of 0.5765.
     */
    private function decimal(mixed $value): ?float
    {
        if (is_float($value) || is_int($value)) {
            return (float) $value;
        }

        return is_string($value) && is_numeric($value) ? (float) $value : null;
    }

    /**
     * @param array<string, mixed> $source
     *
     * @return array<string, mixed>
     */
    private function arr(array $source, string $key): array
    {
        $value = $source[$key] ?? null;

        return is_array($value) ? $value : [];
    }

    /**
     * @param array<string, mixed> $source
     */
    private function str(array $source, string $key): string
    {
        $value = $source[$key] ?? null;

        return is_string($value) ? $value : '';
    }

    /**
     * @param array<string, mixed> $source
     */
    private function nullableStr(array $source, string $key): ?string
    {
        $value = $source[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
