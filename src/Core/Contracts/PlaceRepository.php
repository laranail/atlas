<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Atlas\Core\Contracts;

use Simtabi\Laranail\Atlas\Core\Country\CountryRecord;

/**
 * The seam every data source satisfies.
 *
 * `Generated` (shipped, default), `Rinvex` (optional) and a future `Remote` all
 * implement this, so changing the source never touches a call site. That is the
 * property the old toolkit module lacked: it returned arrays shaped by whatever
 * `rinvex/countries` exposed, so the data package was load-bearing in every
 * consumer, not just in the loader.
 *
 * Deliberately narrow. Everything derivable from the full list — groupings,
 * select options, region indexes, name searches — is computed above this
 * interface rather than demanded of every adapter. A source implements four
 * methods; it does not reimplement the query layer.
 */
interface PlaceRepository
{
    /**
     * Every country, keyed by upper-case ISO 3166-1 alpha-2.
     *
     * @return array<string, CountryRecord>
     */
    public function all(): array;

    /**
     * One country by ISO alpha-2, alpha-3 or numeric code, or null.
     *
     * Returns null rather than throwing: "is this a country code" is a question
     * callers ask about user input, and a null is cheaper than a try/catch. The
     * throwing form lives on the builder, where the caller has already said
     * which country they mean.
     */
    public function find(string $code): ?CountryRecord;

    /**
     * Whether the underlying source is usable right now.
     *
     * Answered without loading the dataset where possible — this is what
     * `doctor` calls, and what the factory checks before handing back an
     * adapter whose data package is missing.
     */
    public function isAvailable(): bool;

    /**
     * A version stamp for the data behind this source.
     *
     * For the shipped dataset that is the build date in
     * `resources/data/dataset-version.txt`; for a remote source, whatever it
     * reports. Null when the source cannot say — which `doctor` reports as
     * unknown rather than as current, because a source that cannot date itself
     * cannot be checked for staleness.
     */
    public function version(): ?string;
}
