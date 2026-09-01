<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Atlas\Adapters\Generated;

use Simtabi\Laranail\Atlas\Core\Contracts\PlaceRepository;
use Simtabi\Laranail\Atlas\Core\Country\CountryRecord;
use Simtabi\Laranail\Atlas\Core\Geo\BoundingBox;
use Simtabi\Laranail\Atlas\Core\Geo\Coordinates;

/**
 * The default data source: the dataset shipped with this package.
 *
 * One `require` of a flat PHP array, which OPcache holds as compiled opcodes —
 * no JSON parsing, no data package, no network. `tools/build-dataset.php` writes
 * it and `--check` gates it, so the file is a pure function of the ISO registers
 * rather than something maintained by hand.
 *
 * Loaded lazily and memoised for the object's life. An application that never
 * asks a country question never pays for the file, and one that asks a thousand
 * pays once.
 */
final class GeneratedPlaceRepository implements PlaceRepository
{
    /** @var array<string, CountryRecord>|null */
    private ?array $countries = null;

    /** Secondary indexes, built with the primary one — see index(). */
    /** @var array<string, string>|null */
    private ?array $byIso3 = null;

    /** @var array<string, string>|null */
    private ?array $byNumeric = null;

    public function __construct(
        private readonly string $dataPath,
    ) {}

    public function all(): array
    {
        return $this->index();
    }

    public function find(string $code): ?CountryRecord
    {
        $code = strtoupper(trim($code));

        if ($code === '') {
            return null;
        }

        $countries = $this->index();

        // Dispatch on length rather than trying each index in turn: the three
        // ISO forms have distinct, non-overlapping lengths, so one comparison
        // answers which register is being addressed.
        return match (strlen($code)) {
            2 => $countries[$code] ?? null,
            3 => $this->byThreeCharacterCode($code, $countries),
            default => null,
        };
    }

    public function isAvailable(): bool
    {
        return is_file($this->dataPath.'/countries.php');
    }

    public function version(): ?string
    {
        $file = $this->dataPath.'/dataset-version.txt';

        if (! is_file($file)) {
            return null;
        }

        $version = trim((string) file_get_contents($file));

        return $version === '' ? null : $version;
    }

    /**
     * Both three-character registers, in one place.
     *
     * ISO alpha-3 and ISO numeric are both three characters, so a `strlen` of 3
     * is ambiguous — `KEN` and `404` arrive the same way. Numeric codes are all
     * digits and alpha-3 codes never are, which separates them without asking
     * the caller to declare which they meant.
     *
     * @param  array<string, CountryRecord>  $countries
     */
    private function byThreeCharacterCode(string $code, array $countries): ?CountryRecord
    {
        $this->index();

        $iso2 = ctype_digit($code)
            ? ($this->byNumeric[$code] ?? null)
            : ($this->byIso3[$code] ?? null);

        return $iso2 === null ? null : ($countries[$iso2] ?? null);
    }

    /**
     * Load the dataset and build every index in one pass.
     *
     * @return array<string, CountryRecord>
     */
    private function index(): array
    {
        if ($this->countries !== null) {
            return $this->countries;
        }

        $file = $this->dataPath.'/countries.php';

        // A missing dataset is not an exception here. isAvailable() is the
        // question that answers it, doctor is what asks, and returning nothing
        // lets a caller distinguish "no such country" from "no data at all"
        // through that method rather than through a catch.
        if (! is_file($file)) {
            return $this->countries = [];
        }

        /** @var array<string, array<string, mixed>> $raw */
        $raw = require $file;

        $countries = [];
        $byIso3 = [];
        $byNumeric = [];

        foreach ($raw as $iso2 => $row) {
            $record = $this->hydrate($row);

            $countries[$iso2] = $record;

            if ($record->iso3 !== '') {
                $byIso3[$record->iso3] = $iso2;
            }

            // Kosovo (XK) is user-assigned and has no ISO numeric, so it is
            // indexed by alpha-3 only. Indexing an empty string would make
            // find('') resolve to whichever such country was read last.
            if ($record->numeric !== '') {
                $byNumeric[$record->numeric] = $iso2;
            }
        }

        $this->byIso3 = $byIso3;
        $this->byNumeric = $byNumeric;

        return $this->countries = $countries;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function hydrate(array $row): CountryRecord
    {
        $latitude = $row['latitude'] ?? null;
        $longitude = $row['longitude'] ?? null;

        $coordinates = is_float($latitude) && is_float($longitude)
            ? new Coordinates($latitude, $longitude)
            : null;

        return new CountryRecord(
            iso2: $this->str($row, 'iso2'),
            iso3: $this->str($row, 'iso3'),
            numeric: $this->str($row, 'numeric'),
            name: $this->str($row, 'name'),
            officialName: $this->str($row, 'official_name'),
            nativeName: $this->str($row, 'native_name'),
            continent: $this->str($row, 'continent'),
            region: $this->nullableStr($row, 'region'),
            subregion: $this->nullableStr($row, 'subregion'),
            currencies: $this->strList($row, 'currencies'),
            languages: $this->strList($row, 'languages'),
            callingCodes: $this->strList($row, 'calling_codes'),
            tld: $this->nullableStr($row, 'tld'),
            coordinates: $coordinates,
            bounds: $this->bounds($row),
        );
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function str(array $row, string $key): string
    {
        $value = $row[$key] ?? null;

        return is_string($value) ? $value : '';
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function nullableStr(array $row, string $key): ?string
    {
        $value = $row[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<string>
     */
    private function strList(array $row, string $key): array
    {
        $value = $row[$key] ?? null;

        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, is_string(...)));
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function bounds(array $row): ?BoundingBox
    {
        $value = $row['bounds'] ?? null;

        if (! is_array($value) || count($value) !== 4) {
            return null;
        }

        [$west, $south, $east, $north] = array_values($value);

        if (! is_float($west) || ! is_float($south) || ! is_float($east) || ! is_float($north)) {
            return null;
        }

        return BoundingBox::fromBbox($west, $south, $east, $north);
    }
}
