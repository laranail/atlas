<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Atlas\Core\Country;

use JsonSerializable;

/**
 * How long a national phone number is for one country, and the pattern to match it.
 *
 * The national number is what follows the calling code: `+254 712345678` has the
 * calling code `254` and the national number `712345678`.
 *
 * Where a country's national number has a well-known fixed or narrow length,
 * that is stated. Where it does not, the bounds fall back to E.164's own limits
 * rather than inventing a figure, and {@see $exact} says which of the two you
 * are looking at. That distinction matters: a form that rejects a valid number
 * because the package guessed is worse than one that accepts a wrong-length one.
 *
 * This validates *shape*, not existence. A number can match perfectly and still
 * belong to nobody; only a carrier lookup answers that.
 */
final readonly class PhoneRules implements JsonSerializable
{
    /**
     * E.164 caps the whole number, calling code included, at 15 digits, and no
     * national numbering plan uses fewer than 4.
     */
    public const int E164_MAX = 15;

    public const int NATIONAL_MIN = 4;

    public function __construct(
        public string $callingCode,
        public int $minLength,
        public int $maxLength,
        public bool $exact,
    ) {}

    /**
     * The rules for a calling code.
     *
     * @param string $callingCode E.164 prefix, with or without the leading +
     */
    public static function forCallingCode(string $callingCode): self
    {
        $code = ltrim($callingCode, '+');
        $known = self::table()[$code] ?? null;

        if ($known !== null) {
            return new self($code, $known[0], $known[1], true);
        }

        return new self(
            $code,
            self::NATIONAL_MIN,
            max(self::NATIONAL_MIN, self::E164_MAX - strlen($code)),
            false,
        );
    }

    /**
     * Whether a national number is a plausible length for this country.
     *
     * The *national* number — what follows the calling code. Pass a full
     * `+254712345678` here and its country digits count towards the length, so
     * a valid number is rejected; {@see acceptsInternationalNumber()} is the one
     * that takes those. The two were called `accepts()` and `matches()`, which
     * is a distinction no call site could see.
     */
    public function acceptsNationalNumber(string $nationalNumber): bool
    {
        $digits = preg_replace('/\D/', '', $nationalNumber) ?? '';
        $length = strlen($digits);

        return $length >= $this->minLength && $length <= $this->maxLength;
    }

    /**
     * A regex matching a full number for this country.
     *
     * Accepts the calling code with or without a `+`, and tolerates the spaces,
     * dashes and brackets people actually type, because rejecting
     * `+254 712 345 678` for its spaces teaches users to distrust the form
     * rather than to fix the number.
     */
    public function internationalPattern(): string
    {
        $code = preg_quote($this->callingCode, '/');

        return sprintf(
            '/^\+?%s[\s\-().]*(?:[\d][\s\-().]*){%d,%d}$/',
            $code,
            $this->minLength,
            $this->maxLength,
        );
    }

    /** Whether a full number, calling code included, matches this country. */
    public function acceptsInternationalNumber(string $number): bool
    {
        return preg_match($this->internationalPattern(), trim($number)) === 1;
    }

    /** @return array{callingCode: string, minLength: int, maxLength: int, exact: bool, pattern: string} */
    public function toArray(): array
    {
        return [
            'callingCode' => $this->callingCode,
            'minLength'   => $this->minLength,
            'maxLength'   => $this->maxLength,
            'exact'       => $this->exact,
            'pattern'     => $this->internationalPattern(),
        ];
    }

    /** @return array{callingCode: string, minLength: int, maxLength: int, exact: bool, pattern: string} */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * National number lengths, by calling code, where they are well established.
     *
     * Deliberately partial. Every entry here is a numbering plan stable enough
     * that a form can rely on it; everything absent gets E.164's bounds and an
     * `exact` of false, which is the honest answer rather than a confident wrong
     * one. Add a row only with a source, not by inference from examples.
     *
     * Keyed by `int|string` because that is what PHP produces: a numeric string
     * key in an array literal is normalised to an integer, so '254' is stored as
     * 254 however it is written. The lookup casts the same way, so it still
     * finds the row; only the declared type would have been a lie.
     *
     * @return array<int|string, array{int, int}>
     */
    private static function table(): array
    {
        return [
            '1'   => [10, 10],     // NANP: US, Canada and the Caribbean
            '7'   => [10, 10],     // Russia, Kazakhstan
            '20'  => [9, 10],     // Egypt
            '27'  => [9, 9],      // South Africa
            '30'  => [10, 10],    // Greece
            '31'  => [9, 9],      // Netherlands
            '32'  => [8, 9],      // Belgium
            '33'  => [9, 9],      // France
            '34'  => [9, 9],      // Spain
            '36'  => [8, 9],      // Hungary
            '39'  => [9, 10],     // Italy
            '40'  => [9, 9],      // Romania
            '41'  => [9, 9],      // Switzerland
            '43'  => [10, 13],    // Austria
            '44'  => [10, 10],    // United Kingdom
            '45'  => [8, 8],      // Denmark
            '46'  => [7, 9],      // Sweden
            '47'  => [8, 8],      // Norway
            '48'  => [9, 9],      // Poland
            '49'  => [10, 11],    // Germany
            '51'  => [9, 9],      // Peru
            '52'  => [10, 10],    // Mexico
            '54'  => [10, 10],    // Argentina
            '55'  => [10, 11],    // Brazil
            '56'  => [9, 9],      // Chile
            '57'  => [10, 10],    // Colombia
            '58'  => [10, 10],    // Venezuela
            '60'  => [9, 10],     // Malaysia
            '61'  => [9, 9],      // Australia
            '62'  => [9, 12],     // Indonesia
            '63'  => [10, 10],    // Philippines
            '64'  => [8, 10],     // New Zealand
            '65'  => [8, 8],      // Singapore
            '66'  => [9, 9],      // Thailand
            '81'  => [10, 10],    // Japan
            '82'  => [9, 10],     // South Korea
            '84'  => [9, 10],     // Vietnam
            '86'  => [11, 11],    // China
            '90'  => [10, 10],    // Turkey
            '91'  => [10, 10],    // India
            '92'  => [10, 10],    // Pakistan
            '93'  => [9, 9],      // Afghanistan
            '94'  => [9, 9],      // Sri Lanka
            '212' => [9, 9],     // Morocco
            '213' => [9, 9],     // Algeria
            '216' => [8, 8],     // Tunisia
            '218' => [9, 9],     // Libya
            '220' => [7, 7],     // Gambia
            '221' => [9, 9],     // Senegal
            '233' => [9, 9],     // Ghana
            '234' => [10, 10],   // Nigeria
            '250' => [9, 9],     // Rwanda
            '251' => [9, 9],     // Ethiopia
            '254' => [9, 9],     // Kenya
            '255' => [9, 9],     // Tanzania
            '256' => [9, 9],     // Uganda
            '260' => [9, 9],     // Zambia
            '263' => [9, 9],     // Zimbabwe
            '351' => [9, 9],     // Portugal
            '353' => [9, 9],     // Ireland
            '358' => [9, 10],    // Finland
            '359' => [8, 9],     // Bulgaria
            '380' => [9, 9],     // Ukraine
            '420' => [9, 9],     // Czechia
            '421' => [9, 9],     // Slovakia
            '852' => [8, 8],     // Hong Kong
            '880' => [10, 10],   // Bangladesh
            '966' => [9, 9],     // Saudi Arabia
            '971' => [9, 9],     // United Arab Emirates
            '972' => [9, 9],     // Israel
            '974' => [8, 8],     // Qatar
            '977' => [10, 10],   // Nepal
        ];
    }
}
