<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Atlas\Core\Country;

use Collator;
use Simtabi\Laranail\Atlas\Core\Contracts\PlaceRepository;
use Simtabi\Laranail\Atlas\Core\Exception\UnknownCountry;
use Simtabi\Laranail\Atlas\Core\Geo\BoundingBox;
use Simtabi\Laranail\Atlas\Core\Geo\Coordinates;
use Simtabi\Laranail\Atlas\Core\Region\Continent;
use Simtabi\Laranail\Atlas\Core\Support\Text;

/**
 * A fluent, immutable query over the country catalogue.
 *
 * The module this replaces exposed fourteen fixed methods — `countries()`,
 * `countriesInContinent()`, `forSelectBox()`, `regions()` — so every question
 * it had not anticipated meant filtering its array output by hand at the call
 * site. Composition is the fix: `inContinent()` and `usingCurrency()` and
 * `whereNameContains()` chain, and the fourteen methods become a handful of
 * terminals.
 *
 * **Immutable**, by clone-on-write. A shared, mutable builder is a bug waiting
 * for a second caller: `$q = Atlas::query()->inContinent(…)` handed to two
 * places, one of which adds a filter, silently changes the other's results.
 * Every method here returns a new instance, so a partially-built query is safe
 * to keep and reuse.
 *
 * Filters are stored as closures and applied once, at the terminal, rather than
 * each narrowing the set eagerly. Chaining five filters therefore walks the 250
 * records once instead of five times, and a query that is built and never
 * resolved costs nothing.
 */
final readonly class CountryQuery
{
    /**
     * @param  list<callable(CountryRecord): bool>  $filters
     * @param  (callable(CountryRecord, CountryRecord): int)|null  $sort
     */
    private function __construct(
        private PlaceRepository $repository,
        private array $filters = [],
        private mixed $sort = null,
        private ?int $limit = null,
    ) {}

    public static function over(PlaceRepository $repository): self
    {
        return new self($repository);
    }

    // -----------------------------------------------------------------------
    // Filters
    // -----------------------------------------------------------------------

    /**
     * Narrow by continent, accepting the enum, a code (`AF`) or a name
     * (`Africa`).
     *
     * A string that matches nothing yields an **empty result**, not every
     * country. Silently ignoring an unrecognised filter is how a typo becomes a
     * page listing the whole world.
     */
    public function inContinent(Continent|string $continent): self
    {
        $resolved = $continent instanceof Continent ? $continent : Continent::resolve($continent);

        if (! $resolved instanceof Continent) {
            return $this->withFilter(static fn (): bool => false);
        }

        return $this->withFilter(static fn (CountryRecord $c): bool => $c->continent === $resolved->value);
    }

    /**
     * Narrow by UN geoscheme region — Africa, Americas, Asia, Europe, Oceania.
     *
     * Not the same axis as {@see inContinent()}: `Americas` is one region and
     * two continents.
     */
    public function inRegion(string $region): self
    {
        $region = trim($region);

        return $this->withFilter(
            static fn (CountryRecord $c): bool => $c->region !== null && strcasecmp($c->region, $region) === 0,
        );
    }

    public function inSubregion(string $subregion): self
    {
        $subregion = trim($subregion);

        return $this->withFilter(
            static fn (CountryRecord $c): bool => $c->subregion !== null && strcasecmp($c->subregion, $subregion) === 0,
        );
    }

    /**
     * Countries where the given ISO 4217 code is legal tender.
     *
     * Matches any of a country's currencies, not only the first — several
     * countries have more than one, and a query for `USD` that missed Panama
     * because the balboa is listed first would be wrong.
     */
    public function usingCurrency(string $code): self
    {
        $code = strtoupper(trim($code));

        return $this->withFilter(static fn (CountryRecord $c): bool => in_array($code, $c->currencies, true));
    }

    public function speakingLanguage(string $code): self
    {
        $code = strtolower(trim($code));

        return $this->withFilter(static fn (CountryRecord $c): bool => in_array($code, $c->languages, true));
    }

    /**
     * Free-text search across the three name forms.
     *
     * Case- and accent-insensitive on the common path: someone typing `cote`
     * should find Côte d'Ivoire, and someone typing `Turkiye` should find
     * Türkiye. Without folding, a country whose name a keyboard cannot easily
     * produce is unreachable by search.
     */
    public function whereNameContains(string $needle): self
    {
        $needle = Text::fold($needle);

        if ($needle === '') {
            return $this;
        }

        return $this->withFilter(static fn (CountryRecord $c): bool => array_any([$c->name, $c->officialName, $c->nativeName], fn (string $candidate): bool => str_contains(Text::fold($candidate), $needle)));
    }

    /**
     * Drop Antarctica and anywhere else with no permanent civilian population.
     *
     * A country picker on a signup form almost never wants it, and remembering
     * to exclude it by hand is exactly the sort of thing nobody does.
     */
    public function inhabitedOnly(): self
    {
        return $this->withFilter(static function (CountryRecord $c): bool {
            $continent = Continent::tryFrom($c->continent);

            return $continent === null || $continent->isInhabited();
        });
    }

    /**
     * Countries whose bounding box contains a point.
     *
     * A box is not a border — it is the rectangle around one, so a point in the
     * Bay of Biscay is "in" both France and Spain. This is a cheap pre-filter,
     * not a geocoder, and countries with no bounds in the source are excluded
     * rather than guessed at.
     */
    public function containing(Coordinates $point): self
    {
        return $this->withFilter(
            static fn (CountryRecord $c): bool => $c->bounds instanceof BoundingBox && $c->bounds->contains($point),
        );
    }

    /**
     * An escape hatch for a question this builder does not anticipate.
     *
     * Its existence is why the filter list above can stay short: a caller with
     * an unusual predicate writes it inline rather than waiting for a method.
     *
     * @param  callable(CountryRecord): bool  $predicate
     */
    public function where(callable $predicate): self
    {
        return $this->withFilter($predicate);
    }

    // -----------------------------------------------------------------------
    // Ordering and limiting
    // -----------------------------------------------------------------------

    /**
     * Sort by common name, using the collator when ext-intl is present.
     *
     * `sort()` on strings compares bytes, which puts every accented name after
     * every unaccented one — Åland after Zimbabwe. That is not an ordering any
     * reader recognises, so a country list sorted that way looks broken.
     */
    public function sortedByName(): self
    {
        return new self(
            $this->repository,
            $this->filters,
            static fn (CountryRecord $a, CountryRecord $b): int => self::compareNames($a->name, $b->name),
            $this->limit,
        );
    }

    public function sortedByCode(): self
    {
        return new self(
            $this->repository,
            $this->filters,
            static fn (CountryRecord $a, CountryRecord $b): int => strcmp($a->iso2, $b->iso2),
            $this->limit,
        );
    }

    public function take(int $limit): self
    {
        return new self($this->repository, $this->filters, $this->sort, max(0, $limit));
    }

    // -----------------------------------------------------------------------
    // Terminals
    // -----------------------------------------------------------------------

    /**
     * @return list<CountryRecord>
     */
    public function get(): array
    {
        $records = array_values(array_filter(
            $this->repository->all(),
            $this->passes(...),
        ));

        if ($this->sort !== null) {
            usort($records, $this->sort);
        }

        return $this->limit === null ? $records : array_slice($records, 0, $this->limit);
    }

    public function first(): ?CountryRecord
    {
        return $this->take(1)->get()[0] ?? null;
    }

    public function count(): int
    {
        return count($this->get());
    }

    public function isEmpty(): bool
    {
        return ! $this->first() instanceof CountryRecord;
    }

    /**
     * One country by code, or null.
     *
     * Bypasses the filters deliberately: this asks the repository directly,
     * because "give me KE" is a lookup and not a query, and applying a
     * half-built query's filters to it would be surprising.
     */
    public function find(string $code): ?CountryRecord
    {
        return $this->repository->find($code);
    }

    /**
     * One country by code, or throw.
     *
     * The throwing twin of {@see find()}, for when the caller has already said
     * which country they mean and a null would only be dereferenced.
     */
    public function findOrFail(string $code): CountryRecord
    {
        return $this->find($code) ?? throw UnknownCountry::code($code);
    }

    /**
     * One country by name, or null.
     *
     * Exact and case-insensitive, across the common, official and native names,
     * so a stored "Cote d'Ivoire" and a typed "Republique de Cote d'Ivoire"
     * arrive at the same record. Use {@see whereNameContains()} for a search
     * box; this is for when the caller already has a name.
     */
    public function findByName(string $name): ?CountryRecord
    {
        $needle = mb_strtolower(trim($name));

        foreach ($this->repository->all() as $country) {
            foreach ([$country->name, $country->officialName, $country->nativeName] as $candidate) {
                if (mb_strtolower($candidate) === $needle) {
                    return $country;
                }
            }
        }

        return null;
    }

    /**
     * One country by calling code, or null.
     *
     * The first match wins, and codes are legitimately shared: +1 is the whole
     * North American Numbering Plan and +7 is Russia and Kazakhstan. Use
     * {@see allByDialCode()} when that matters.
     */
    public function findByDialCode(string $dialCode): ?CountryRecord
    {
        return $this->allByDialCode($dialCode)[0] ?? null;
    }

    /**
     * Every country sharing a calling code.
     *
     * @return list<CountryRecord>
     */
    public function allByDialCode(string $dialCode): array
    {
        $needle = ltrim(trim($dialCode), '+');

        return array_values(array_filter(
            $this->repository->all(),
            static fn (CountryRecord $country): bool => in_array($needle, $country->callingCodes, true),
        ));
    }

    /** The phone rules for a country code, or null when it is not a country. */
    public function phoneRulesFor(string $code): ?PhoneRules
    {
        return $this->find($code)?->phoneRules();
    }

    /** The phone rules for a calling code, whichever country answers to it. */
    public function phoneRulesForDialCode(string $dialCode): PhoneRules
    {
        return PhoneRules::forCallingCode($dialCode);
    }

    /**
     * This query's results as `value => label` maps, for a form.
     *
     * The presentation shapes live on {@see FormData} rather than here, because
     * a builder terminal that returns a select box's data is a different kind of
     * answer from one that returns records, and mixing them left `options()`
     * sitting between `get()` and `count()` as the only method whose return
     * value was a display decision.
     *
     * The query is name-sorted on the way in unless the caller chose an order,
     * so a select box is never in dataset order by accident.
     */
    public function form(): FormData
    {
        return new FormData($this->sortedByNameIfUnsorted());
    }

    /**
     * Group the results by continent code.
     *
     * Every continent appears, including ones the filters emptied — a grouped
     * select box with a heading missing is harder to read than one with an
     * empty group, and the caller can drop empties if it prefers.
     *
     * @return array<string, list<CountryRecord>>
     */
    public function groupedByContinent(): array
    {
        $grouped = [];

        foreach (Continent::cases() as $continent) {
            $grouped[$continent->value] = [];
        }

        foreach ($this->sortedByNameIfUnsorted()->get() as $country) {
            $grouped[$country->continent][] = $country;
        }

        return $grouped;
    }

    /**
     * The distinct regions present in the results, sorted.
     *
     * @return list<string>
     */
    public function regions(): array
    {
        return $this->distinct(static fn (CountryRecord $c): ?string => $c->region);
    }

    /**
     * @return list<string>
     */
    public function subregions(): array
    {
        return $this->distinct(static fn (CountryRecord $c): ?string => $c->subregion);
    }

    /**
     * The distinct ISO 4217 codes in use across the results, sorted.
     *
     * Derived from the countries rather than from a separate currency register,
     * so it can never list a currency no country uses.
     *
     * @return list<string>
     */
    public function currencies(): array
    {
        $codes = [];

        foreach ($this->get() as $country) {
            foreach ($country->currencies as $code) {
                $codes[$code] = true;
            }
        }

        $list = array_keys($codes);
        sort($list);

        return $list;
    }

    /**
     * @return list<string>
     */
    public function languages(): array
    {
        $codes = [];

        foreach ($this->get() as $country) {
            foreach ($country->languages as $code) {
                $codes[$code] = true;
            }
        }

        $list = array_keys($codes);
        sort($list);

        return $list;
    }

    /**
     * Locale-aware where possible, byte-wise where not.
     *
     * ext-intl is not a hard requirement of this package, so the collator is
     * used when present and `strcmp` is the documented fallback rather than a
     * silent one — see the note on {@see sortedByName()}.
     */
    private static function compareNames(string $a, string $b): int
    {
        if (class_exists(Collator::class)) {
            $collator = new Collator('root');
            $result = $collator->compare($a, $b);

            if ($result !== false) {
                return $result;
            }
        }

        return strcmp($a, $b);
    }

    // -----------------------------------------------------------------------
    // Internals
    // -----------------------------------------------------------------------

    /**
     * @param  callable(CountryRecord): bool  $filter
     */
    private function withFilter(callable $filter): self
    {
        return new self($this->repository, [...$this->filters, $filter], $this->sort, $this->limit);
    }

    private function passes(CountryRecord $record): bool
    {
        return array_all($this->filters, fn (callable $filter) => $filter($record));
    }

    /**
     * Presentation terminals sort by name unless the caller chose an order, so
     * a select box is never in dataset order by accident.
     */
    private function sortedByNameIfUnsorted(): self
    {
        return $this->sort === null ? $this->sortedByName() : $this;
    }

    /**
     * @param  callable(CountryRecord): ?string  $accessor
     * @return list<string>
     */
    private function distinct(callable $accessor): array
    {
        $seen = [];

        foreach ($this->get() as $country) {
            $value = $accessor($country);

            if ($value !== null && $value !== '') {
                $seen[$value] = true;
            }
        }

        $list = array_keys($seen);
        sort($list);

        return $list;
    }
}
