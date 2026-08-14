<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Atlas\Core\Support;

use Transliterator;

/**
 * Case- and accent-folding for search.
 *
 * ## Why not `iconv('UTF-8', 'ASCII//TRANSLIT', …)`
 *
 * Because its output depends on the C library, and the two disagree in a way
 * that turns a working search into a broken one:
 *
 * ```
 * "Côte d'Ivoire"  →  glibc (Linux):  "Cote d'Ivoire"
 *                  →  BSD   (macOS):  "C^ote d'Ivoire"
 * ```
 *
 * The BSD form separates the accent into a literal `^`, so `str_contains(fold(…),
 * 'cote')` is false and the country cannot be found by typing its name without
 * accents. This was written that way first and caught only because the tests ran
 * on macOS — on a glibc CI runner it passes, and every developer on a Mac sees a
 * search that silently misses.
 *
 * So: ICU's `Transliterator` when ext-intl is present, and an explicit table
 * otherwise. The table is finite and platform-independent, which is the property
 * that matters — a fold that works everywhere and covers less beats one that
 * covers more on some machines.
 */
final class Text
{
    /**
     * Latin letters that carry a diacritic, and their unaccented form.
     *
     * Covers Latin-1 Supplement and Latin Extended-A, which is what country
     * names in this dataset actually use. Not a general Unicode fold: a name in
     * a non-Latin script is left alone rather than mangled, since there is no
     * ASCII form of it a user would type.
     */
    private const array TRANSLITERATIONS = [
        'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A', 'Å' => 'A', 'Ā' => 'A', 'Ă' => 'A', 'Ą' => 'A',
        'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a', 'ā' => 'a', 'ă' => 'a', 'ą' => 'a',
        'Æ' => 'AE', 'æ' => 'ae',
        'Ç' => 'C', 'Ć' => 'C', 'Ĉ' => 'C', 'Ċ' => 'C', 'Č' => 'C',
        'ç' => 'c', 'ć' => 'c', 'ĉ' => 'c', 'ċ' => 'c', 'č' => 'c',
        'Ď' => 'D', 'Đ' => 'D', 'ď' => 'd', 'đ' => 'd',
        'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ë' => 'E', 'Ē' => 'E', 'Ĕ' => 'E', 'Ė' => 'E', 'Ę' => 'E', 'Ě' => 'E',
        'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e', 'ē' => 'e', 'ĕ' => 'e', 'ė' => 'e', 'ę' => 'e', 'ě' => 'e',
        'Ĝ' => 'G', 'Ğ' => 'G', 'Ġ' => 'G', 'Ģ' => 'G',
        'ĝ' => 'g', 'ğ' => 'g', 'ġ' => 'g', 'ģ' => 'g',
        'Ĥ' => 'H', 'Ħ' => 'H', 'ĥ' => 'h', 'ħ' => 'h',
        'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I', 'Ĩ' => 'I', 'Ī' => 'I', 'Ĭ' => 'I', 'Į' => 'I', 'İ' => 'I',
        'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i', 'ĩ' => 'i', 'ī' => 'i', 'ĭ' => 'i', 'į' => 'i', 'ı' => 'i',
        'Ĵ' => 'J', 'ĵ' => 'j', 'Ķ' => 'K', 'ķ' => 'k',
        'Ĺ' => 'L', 'Ļ' => 'L', 'Ľ' => 'L', 'Ł' => 'L',
        'ĺ' => 'l', 'ļ' => 'l', 'ľ' => 'l', 'ł' => 'l',
        'Ñ' => 'N', 'Ń' => 'N', 'Ņ' => 'N', 'Ň' => 'N',
        'ñ' => 'n', 'ń' => 'n', 'ņ' => 'n', 'ň' => 'n',
        'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O', 'Ø' => 'O', 'Ō' => 'O', 'Ŏ' => 'O', 'Ő' => 'O',
        'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o', 'ø' => 'o', 'ō' => 'o', 'ŏ' => 'o', 'ő' => 'o',
        'Œ' => 'OE', 'œ' => 'oe',
        'Ŕ' => 'R', 'Ŗ' => 'R', 'Ř' => 'R', 'ŕ' => 'r', 'ŗ' => 'r', 'ř' => 'r',
        'Ś' => 'S', 'Ŝ' => 'S', 'Ş' => 'S', 'Š' => 'S',
        'ś' => 's', 'ŝ' => 's', 'ş' => 's', 'š' => 's', 'ß' => 'ss',
        'Ţ' => 'T', 'Ť' => 'T', 'Ŧ' => 'T', 'ţ' => 't', 'ť' => 't', 'ŧ' => 't',
        'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U', 'Ũ' => 'U', 'Ū' => 'U', 'Ŭ' => 'U', 'Ů' => 'U', 'Ű' => 'U', 'Ų' => 'U',
        'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u', 'ũ' => 'u', 'ū' => 'u', 'ŭ' => 'u', 'ů' => 'u', 'ű' => 'u', 'ų' => 'u',
        'Ŵ' => 'W', 'ŵ' => 'w',
        'Ý' => 'Y', 'Ŷ' => 'Y', 'Ÿ' => 'Y', 'ý' => 'y', 'ŷ' => 'y', 'ÿ' => 'y',
        'Ź' => 'Z', 'Ż' => 'Z', 'Ž' => 'Z', 'ź' => 'z', 'ż' => 'z', 'ž' => 'z',
        'Þ' => 'TH', 'þ' => 'th', 'Ð' => 'D', 'ð' => 'd',
    ];

    private static ?Transliterator $transliterator = null;

    private static bool $transliteratorResolved = false;

    /**
     * Stripped of diacritics, with case preserved.
     *
     * Separate from {@see fold()} because the enum generator needs `Åland` to
     * become `Aland` and not `aland` — a case name has to keep its capitals.
     * It uses the table unconditionally rather than ICU: the generator's output
     * is committed and gated by `--check`, so it must be identical on every
     * machine, and ICU's transliteration can differ between versions.
     */
    public static function transliterate(string $value): string
    {
        return strtr($value, self::TRANSLITERATIONS);
    }

    /**
     * Lower-cased and stripped of diacritics, for comparison.
     */
    public static function fold(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        $transliterator = self::transliterator();

        if ($transliterator instanceof Transliterator) {
            $result = $transliterator->transliterate($value);

            if (is_string($result)) {
                return $result;
            }
        }

        return mb_strtolower(strtr($value, self::TRANSLITERATIONS), 'UTF-8');
    }

    /**
     * Resolved once. `Transliterator::create()` returns null for a rule set ICU
     * does not have, so the result is cached including the null — otherwise a
     * failing create is retried on every folded string.
     */
    private static function transliterator(): ?Transliterator
    {
        if (self::$transliteratorResolved) {
            return self::$transliterator;
        }

        self::$transliteratorResolved = true;

        if (! class_exists(Transliterator::class)) {
            return self::$transliterator = null;
        }

        return self::$transliterator = Transliterator::create('Any-Latin; Latin-ASCII; Lower');
    }
}
