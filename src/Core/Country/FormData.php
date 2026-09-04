<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Atlas\Core\Country;

use Simtabi\Laranail\Atlas\Core\Region\Continent;
use Simtabi\Laranail\Atlas\Core\Contracts\PlaceRepository;

/**
 * The catalogue as a form sees it: `value => label` maps, ready for a `<select>`.
 *
 * A separate object rather than more methods on the service, because the two
 * answer different questions and were returning different shapes under names
 * that did not say so. `Atlas::continents()` handed back a `code => label` map
 * while `Atlas::regions()` beside it handed back a flat list — same phrasing,
 * different contract, and the only way to find out was to `dd()` it. Everything
 * behind `form()` returns a map keyed by what a form submits; everything on the
 * service returns records or plain lists.
 *
 * Every method here is presentation, so every method sorts. A select box in
 * dataset order looks broken to a reader who has no idea what the dataset's
 * order is.
 */
final readonly class FormData
{
    public function __construct(
        private CountryQuery $query,
    ) {}

    public static function over(PlaceRepository $repository): self
    {
        return new self(CountryQuery::over($repository)->sortedByName());
    }

    /**
     * Countries as `code => label`.
     *
     * @param 'iso2'|'iso3'|'numeric' $key what the form submits
     * @param 'name'|'officialName'|'nativeName' $label what the reader sees
     *
     * @return array<string, string>
     */
    public function options(string $key = 'iso2', string $label = 'name'): array
    {
        $options = [];

        foreach ($this->query->get() as $country) {
            $optionKey = $this->keyFor($country, $key);

            // A country with no numeric code (XK) would otherwise collapse into
            // a single empty-string key, silently dropping every other such
            // country from the list.
            if ($optionKey === '') {
                continue;
            }

            $options[$optionKey] = $this->labelFor($country, $label);
        }

        return $options;
    }

    /**
     * The same options, nested one level for `<optgroup>`.
     *
     * Keyed by the continent's display name rather than its code, because an
     * optgroup label is read by a person and `NA` is not a heading. Continents
     * the filters emptied are dropped here — an empty `<optgroup>` renders as a
     * heading with nothing under it, which is worse than its absence.
     *
     * @param 'iso2'|'iso3'|'numeric' $key
     * @param 'name'|'officialName'|'nativeName' $label
     *
     * @return array<string, array<string, string>>
     */
    public function groupedOptions(string $key = 'iso2', string $label = 'name'): array
    {
        $grouped = [];

        foreach (Continent::cases() as $continent) {
            $grouped[$continent->label()] = [];
        }

        foreach ($this->query->get() as $country) {
            $optionKey = $this->keyFor($country, $key);

            if ($optionKey === '') {
                continue;
            }

            $continent = Continent::tryFrom($country->continent);

            if (! $continent instanceof Continent) {
                continue;
            }

            $grouped[$continent->label()][$optionKey] = $this->labelFor($country, $label);
        }

        return array_filter($grouped, static fn (array $options): bool => $options !== []);
    }

    /**
     * Continents as `code => label`.
     *
     * From the enum rather than from config. The module this package replaces
     * read a `laranail.toolkit.atlas.continents` map, so an application that
     * published the config and trimmed the list got a country grouping that
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
     * Calling codes as `+254 => 'Kenya (+254)'`.
     *
     * Keyed by the code with its `+`, since that is what a phone field wants
     * submitted, and labelled with the country because a bare `+1` in a select
     * tells a user nothing. Shared codes appear once per country — +1 is the
     * whole North American Numbering Plan, so the map is keyed by country code
     * instead and carries the dial code in the label.
     *
     * @return array<string, string>
     */
    public function dialCodes(): array
    {
        $options = [];

        foreach ($this->query->get() as $country) {
            $code = $country->callingCode();

            if ($code === null) {
                continue;
            }

            $options[$country->iso2] = sprintf('%s (+%s)', $country->name, $code);
        }

        return $options;
    }

    /**
     * Currencies as `code => code`.
     *
     * Value and label are the same string because the dataset carries no
     * currency names, and inventing them here would mean shipping a second
     * register to drift against the first. `laranail/enumerator` or an
     * application's own translations are where a display name belongs.
     *
     * @return array<string, string>
     */
    public function currencies(): array
    {
        return $this->identityMap($this->query->currencies());
    }

    /**
     * Languages as `code => code`, for the same reason as {@see currencies()}.
     *
     * The codes are ISO 639-3 (`eng`, `swa`), not the two-letter forms a locale
     * string uses.
     *
     * @return array<string, string>
     */
    public function languages(): array
    {
        return $this->identityMap($this->query->languages());
    }

    /**
     * UN geoscheme regions as `name => name`.
     *
     * @return array<string, string>
     */
    public function regions(): array
    {
        return $this->identityMap($this->query->regions());
    }

    /**
     * @return array<string, string>
     */
    public function subregions(): array
    {
        return $this->identityMap($this->query->subregions());
    }

    /**
     * @param 'iso2'|'iso3'|'numeric' $key
     */
    private function keyFor(CountryRecord $country, string $key): string
    {
        return match ($key) {
            'iso3'    => $country->iso3,
            'numeric' => $country->numeric,
            default   => $country->iso2,
        };
    }

    /**
     * @param 'name'|'officialName'|'nativeName' $label
     */
    private function labelFor(CountryRecord $country, string $label): string
    {
        return match ($label) {
            'officialName' => $country->officialName,
            'nativeName'   => $country->nativeName,
            default        => $country->name,
        };
    }

    /**
     * @param list<string> $values
     *
     * @return array<string, string>
     */
    private function identityMap(array $values): array
    {
        return array_combine($values, $values);
    }
}
