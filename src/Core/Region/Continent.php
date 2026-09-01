<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Atlas\Core\Region;

/**
 * The seven continent codes, as the ISO-adjacent two-letter form.
 *
 * A plain native enum with no `laranail/enumerator` decoration, because this
 * lives in `src/Core` and Core references no other laranail package — that is
 * what deptrac enforces and what keeps the domain extractable. The Laravel-side
 * enums under `src/Enums` are free to be richer.
 *
 * Note the difference from **region**, which the dataset also carries: regions
 * are the five UN geoscheme groupings (Africa, Americas, Asia, Europe, Oceania)
 * and put North and South America together, while continents separate them and
 * add Antarctica. Neither is a refinement of the other, so both are kept.
 */
enum Continent: string
{
    case Africa = 'AF';
    case Antarctica = 'AN';
    case Asia = 'AS';
    case Europe = 'EU';
    case NorthAmerica = 'NA';
    case Oceania = 'OC';
    case SouthAmerica = 'SA';

    /**
     * Resolve a code or an English name, case-insensitively.
     *
     * Both forms are accepted because both arrive: `AF` from a database column,
     * `Africa` from a form or a URL segment. Returns null rather than throwing —
     * this is asked of user input.
     *
     * `NA` is the trap. PHP's own `tryFrom` handles it fine, but a caller that
     * pre-processes with something loosely typed can turn it into a null before
     * it ever gets here; nothing in this method does that.
     */
    public static function resolve(string $value): ?self
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        $byCode = self::tryFrom(strtoupper($value));

        if ($byCode instanceof self) {
            return $byCode;
        }

        foreach (self::cases() as $case) {
            if (strcasecmp($case->label(), $value) === 0) {
                return $case;
            }
        }

        return null;
    }

    /**
     * @return array<string, string> code => label, for a select box
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }

    /**
     * The English display name.
     *
     * Hard-coded rather than derived from the case name, because splitting
     * `NorthAmerica` on capitals is a transformation that works until a case
     * name contains an acronym, and a seven-entry match is cheaper to read than
     * the regex that would replace it.
     */
    public function label(): string
    {
        return match ($this) {
            self::Africa => 'Africa',
            self::Antarctica => 'Antarctica',
            self::Asia => 'Asia',
            self::Europe => 'Europe',
            self::NorthAmerica => 'North America',
            self::Oceania => 'Oceania',
            self::SouthAmerica => 'South America',
        };
    }

    /**
     * Whether this continent has a permanent civilian population.
     *
     * Antarctica is in the dataset with five entries and no currency, which is
     * correct and routinely unwanted in a country picker.
     */
    public function isInhabited(): bool
    {
        return $this !== self::Antarctica;
    }
}
