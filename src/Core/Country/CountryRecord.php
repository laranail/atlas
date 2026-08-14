<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Atlas\Core\Country;

use JsonSerializable;
use Simtabi\Laranail\Atlas\Core\Geo\BoundingBox;
use Simtabi\Laranail\Atlas\Core\Geo\Coordinates;

/**
 * One country, as every data source must present it.
 *
 * This is the contract between the adapters and everything above them. A source
 * that cannot fill a field puts null or an empty list in it — never a plausible
 * guess, and never a partially-built object. The old toolkit module returned
 * bare arrays shaped by whatever `rinvex/countries` happened to expose, so
 * swapping the source was a breaking change for every call site; this is what
 * makes the source swappable.
 *
 * Readonly because a country is a fact, not a state. Anything that varies by
 * request — a translated name, a formatted distance — is computed by the caller
 * from these values rather than stored here.
 */
final readonly class CountryRecord implements JsonSerializable
{
    /**
     * @param string $iso2 ISO 3166-1 alpha-2, upper case (KE)
     * @param string $iso3 ISO 3166-1 alpha-3, upper case (KEN)
     * @param string $numeric ISO 3166-1 numeric, zero-padded to three (404)
     * @param string $name common English name
     * @param string $officialName full official English name
     * @param string $nativeName name in a national language
     * @param string $continent continent code (AF)
     * @param string|null $region UN geoscheme region
     * @param string|null $subregion UN geoscheme subregion
     * @param list<string> $currencies ISO 4217 codes; several countries share one
     * @param list<string> $languages ISO 639-1 codes
     * @param list<string> $callingCodes E.164 prefixes without the leading +
     * @param string|null $tld primary ccTLD including the dot
     * @param Coordinates|null $coordinates approximate centroid
     * @param BoundingBox|null $bounds extent, where the source carries one
     */
    public function __construct(
        public string $iso2,
        public string $iso3,
        public string $numeric,
        public string $name,
        public string $officialName,
        public string $nativeName,
        public string $continent,
        public ?string $region = null,
        public ?string $subregion = null,
        public array $currencies = [],
        public array $languages = [],
        public array $callingCodes = [],
        public ?string $tld = null,
        public ?Coordinates $coordinates = null,
        public ?BoundingBox $bounds = null,
    ) {}

    /**
     * The regional-indicator pair that renders as a flag.
     *
     * Derived rather than stored: every ISO2 code maps to exactly one pair by
     * offsetting each letter into U+1F1E6..U+1F1FF, so storing it would be
     * storing a function of a field we already have — and a field that can go
     * stale against the code beside it.
     */
    public function flag(): string
    {
        if (strlen($this->iso2) !== 2) {
            return '';
        }

        $offset = 0x1F1E6 - ord('A');

        return mb_chr(ord($this->iso2[0]) + $offset, 'UTF-8')
            . mb_chr(ord($this->iso2[1]) + $offset, 'UTF-8');
    }

    /**
     * The primary currency, or null when the source lists none.
     *
     * "Primary" is first-declared. Several countries genuinely have more than
     * one legal tender and this picks one, so read `$currencies` when that
     * matters.
     */
    public function currency(): ?string
    {
        return $this->currencies[0] ?? null;
    }

    /**
     * The primary calling code, without the leading `+`.
     */
    public function callingCode(): ?string
    {
        return $this->callingCodes[0] ?? null;
    }

    /**
     * How long a phone number here is, and the pattern that matches one.
     *
     * Null for the few territories with no calling code of their own, rather
     * than rules that would match nothing.
     */
    public function phoneRules(): ?PhoneRules
    {
        $code = $this->callingCode();

        return $code === null ? null : PhoneRules::forCallingCode($code);
    }

    /** Whether a full phone number, calling code included, fits this country. */
    public function acceptsInternationalNumber(string $number): bool
    {
        return $this->phoneRules()?->acceptsInternationalNumber($number) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'iso2' => $this->iso2,
            'iso3' => $this->iso3,
            'numeric' => $this->numeric,
            'name' => $this->name,
            'official_name' => $this->officialName,
            'native_name' => $this->nativeName,
            'flag' => $this->flag(),
            'continent' => $this->continent,
            'region' => $this->region,
            'subregion' => $this->subregion,
            'currencies' => $this->currencies,
            'languages' => $this->languages,
            'calling_codes' => $this->callingCodes,
            'tld' => $this->tld,
            'coordinates' => $this->coordinates?->toArray(),
            'bounds' => $this->bounds?->toBbox(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
