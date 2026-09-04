<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Atlas\Services;

use Simtabi\Laranail\Atlas\Core\Contracts\PlaceRepository;

/**
 * Which translation locales an application actually ships.
 *
 * ## The bug this is a rewrite of
 *
 * The toolkit module scanned `resource_path('lang')`. **Laravel moved that
 * directory to the project root in version 9**, so on every modern application
 * the path does not exist and `availableLocales()` returned `[]` — a language
 * switcher with nothing in it, on a package whose job is to populate one.
 *
 * It went unnoticed because the test asserting the behaviour created
 * `resource_path('lang')` itself in `setUp()` before scanning it. The scan then
 * found what the test had just put there, and the assertion passed against a
 * directory no real application has.
 *
 * Both layouts are searched here. `lang_path()` is where Laravel 13 puts it and
 * is checked first; `resources/lang` is checked too, because an application
 * that upgraded through 9 without moving the directory still works, and finding
 * it is free.
 */
final readonly class LocaleRegistry
{
    /**
     * @param list<string> $searchPaths absolute paths, in priority order
     */
    public function __construct(
        private array $searchPaths,
        private PlaceRepository $repository,
    ) {}

    /**
     * The locale directories present on disk.
     *
     * Directory names as they appear — `en`, `en_GB`, `pt-BR` — with `vendor`
     * skipped, since that holds published package translations rather than
     * application locales.
     *
     * @return list<string>
     */
    public function installed(): array
    {
        $locales = [];

        foreach ($this->searchPaths as $path) {
            foreach ($this->scan($path) as $locale) {
                $locales[$locale] = true;
            }
        }

        $list = array_keys($locales);
        sort($list);

        return $list;
    }

    /**
     * Installed locales enriched with the country they most likely belong to.
     *
     * `en_GB` resolves through its region subtag to the United Kingdom, so a
     * switcher can show a flag beside the name. A locale with no region subtag
     * (`en`) has no country — deliberately null rather than guessed at, because
     * guessing means picking one nation's flag for a language many speak, and
     * that is a choice a package should not make on an application's behalf.
     *
     * @return array<string, array{locale: string, language: string, region: ?string, country: ?string, flag: ?string}>
     */
    public function detailed(): array
    {
        $described = [];

        foreach ($this->installed() as $locale) {
            [$language, $region] = $this->split($locale);

            $country = $region === null ? null : $this->repository->find($region);

            $described[$locale] = [
                'locale'   => $locale,
                'language' => $language,
                'region'   => $region,
                'country'  => $country?->name,
                'flag'     => $country?->flag(),
            ];
        }

        return $described;
    }

    public function has(string $locale): bool
    {
        return in_array($locale, $this->installed(), true);
    }

    /**
     * Split a locale into its language and region subtags.
     *
     * Accepts both separators: BCP 47 says `en-GB` and PHP/Laravel conventions
     * say `en_GB`, and both turn up as directory names in the wild.
     *
     * @return array{0: string, 1: ?string}
     */
    private function split(string $locale): array
    {
        $parts = preg_split('/[-_]/', $locale, 2) ?: [$locale];

        $language = strtolower($parts[0]);
        $region = isset($parts[1]) && $parts[1] !== '' ? strtoupper($parts[1]) : null;

        return [$language, $region];
    }

    /**
     * @return list<string>
     */
    private function scan(string $path): array
    {
        if (! is_dir($path)) {
            return [];
        }

        $entries = scandir($path);

        if ($entries === false) {
            return [];
        }

        $locales = [];

        foreach ($entries as $name) {
            if (in_array($name, ['.', '..', 'vendor'], true)) {
                continue;
            }

            if (is_dir($path . DIRECTORY_SEPARATOR . $name)) {
                $locales[] = $name;
            }
        }

        return $locales;
    }
}
