<?php

declare(strict_types=1);

/**
 * Builds `resources/data/countries.php` from the ISO registers.
 *
 *   php tools/build-dataset.php            write the dataset
 *   php tools/build-dataset.php --check    exit 1 if the file on disk differs
 *
 * ## Why generate rather than depend
 *
 * `rinvex/countries` ships ~17 MB across 252 long-list JSON files, translations,
 * flags, geodata and administrative divisions. This package needs a few fields
 * from each and answers questions that never touch the rest, so requiring it
 * would make every consumer carry 17 MB to look up what KE is called. Reading it
 * here, at build time, and emitting one flat PHP array gives the same answers
 * from a file OPcache holds as compiled opcodes.
 *
 * That is the same trade `laranail/chrono` makes with tzdata, and the same
 * discipline applies: the output is a pure function of the input, `--check`
 * proves it, and the source release is stamped so staleness is answerable.
 *
 * ## Source
 *
 * `rinvex/countries` is a **dev-time** input, not a dependency. It is resolved
 * from this package's own vendor when present, and otherwise from a sibling
 * package that already carries it — which is how this runs before atlas has
 * dependencies of its own installed.
 *
 * The `Rinvex` adapter is a separate thing: it reads the live package at
 * runtime for consumers who already have it and want its full long-list.
 */
$root = dirname(__DIR__);
$check = in_array('--check', $argv, true);

$candidates = [
    $root . '/vendor/rinvex/countries/resources',
    dirname($root) . '/toolkit/vendor/rinvex/countries/resources',
];

$resources = null;
foreach ($candidates as $candidate) {
    if (is_dir($candidate . '/data')) {
        $resources = $candidate;

        break;
    }
}

if ($resources === null) {
    fwrite(STDERR, "Cannot find the rinvex/countries resources. Looked in:\n  " . implode("\n  ", $candidates) . "\n\n"
        . "Run `composer require --dev rinvex/countries` in this package, or build from a sibling that has it.\n");

    exit(1);
}

/**
 * Read a coordinate that the source may give as a number or as a string.
 *
 * The long list carries `latitude` as a DMS string ("1 00 N") and
 * `latitude_desc` as a decimal string — the decimal one is what we want, and it
 * is a *string*, so a bare cast on the wrong field silently yields 1.0 for
 * Kenya instead of 0.5765.
 */
$decimal = static function (mixed $value): ?float {
    if (is_float($value) || is_int($value)) {
        return (float) $value;
    }

    if (! is_string($value) || trim($value) === '') {
        return null;
    }

    return is_numeric($value) ? (float) $value : null;
};

/** @var array<string, array<string, mixed>> $countries */
$countries = [];
$files = glob($resources . '/data/*.json') ?: [];
sort($files);

foreach ($files as $file) {
    $raw = json_decode((string) file_get_contents($file), true);

    if (! is_array($raw)) {
        fwrite(STDERR, "Unreadable source file: {$file}\n");

        exit(1);
    }

    $iso2 = strtoupper((string) ($raw['iso_3166_1_alpha2'] ?? ''));

    if ($iso2 === '') {
        continue;
    }

    $name = is_array($raw['name'] ?? null) ? $raw['name'] : [];
    $geo = is_array($raw['geo'] ?? null) ? $raw['geo'] : [];
    $dialling = is_array($raw['dialling'] ?? null) ? $raw['dialling'] : [];

    // The native block is keyed by ISO 639-3 and each entry has its own
    // common/official pair. Take the first — several countries list more than
    // one national language and any choice here is arbitrary, so it is made
    // once, visibly, rather than differently at each call site.
    $native = '';
    if (is_array($name['native'] ?? null)) {
        foreach ($name['native'] as $entry) {
            if (is_array($entry) && is_string($entry['common'] ?? null)) {
                $native = $entry['common'];

                break;
            }
        }
    }

    $continent = '';
    if (is_array($geo['continent'] ?? null)) {
        $continent = (string) (array_key_first($geo['continent']) ?? '');
    }

    $bounds = [];
    $min = [$decimal($geo['min_longitude'] ?? null), $decimal($geo['min_latitude'] ?? null)];
    $max = [$decimal($geo['max_longitude'] ?? null), $decimal($geo['max_latitude'] ?? null)];

    if (! in_array(null, [...$min, ...$max], true)) {
        // Longitude first, matching GeoJSON's bbox order, because that is what
        // anything consuming a bounding box expects to receive.
        $bounds = [$min[0], $min[1], $max[0], $max[1]];
    }

    $latitude = $decimal($geo['latitude_desc'] ?? null);
    $longitude = $decimal($geo['longitude_desc'] ?? null);

    $currencies = is_array($raw['currency'] ?? null) ? array_keys($raw['currency']) : [];
    $languages = is_array($raw['languages'] ?? null) ? array_keys($raw['languages']) : [];
    $calling = is_array($dialling['calling_code'] ?? null) ? $dialling['calling_code'] : [];
    $tld = is_array($raw['tld'] ?? null) ? $raw['tld'] : [];

    // Zero-pad a numeric that exists; leave one that does not as empty. Padding
    // an absent value gives '000', which reads as a real ISO code and is not
    // one — XK (Kosovo) is user-assigned and has no numeric at all.
    $rawNumeric = trim((string) ($raw['iso_3166_1_numeric'] ?? ''));
    $numeric = $rawNumeric === '' ? '' : str_pad($rawNumeric, 3, '0', STR_PAD_LEFT);

    $countries[$iso2] = [
        'iso2' => $iso2,
        'iso3' => strtoupper((string) ($raw['iso_3166_1_alpha3'] ?? '')),
        'numeric' => $numeric,
        'name' => (string) ($name['common'] ?? ''),
        'official_name' => (string) ($name['official'] ?? ''),
        'native_name' => $native !== '' ? $native : (string) ($name['common'] ?? ''),
        'continent' => $continent,
        'region' => is_string($geo['region'] ?? null) && $geo['region'] !== '' ? $geo['region'] : null,
        'subregion' => is_string($geo['subregion'] ?? null) && $geo['subregion'] !== '' ? $geo['subregion'] : null,
        'currencies' => array_values(array_map(strtoupper(...), array_filter($currencies, is_string(...)))),
        'languages' => array_values(array_filter($languages, is_string(...))),
        'calling_codes' => array_values(array_map(strval(...), array_filter($calling, static fn ($c): bool => is_string($c) || is_int($c)))),
        'tld' => isset($tld[0]) && is_string($tld[0]) ? $tld[0] : null,
        'latitude' => $latitude,
        'longitude' => $longitude,
        'bounds' => $bounds,
    ];
}

ksort($countries);

if ($countries === []) {
    fwrite(STDERR, "No countries were read; refusing to write an empty dataset.\n");

    exit(1);
}

// Read the version from the installed.json that governs the resources actually
// used, not this package's own — the source may well be a sibling's vendor, and
// stamping the local one would name a version that had no part in the build.
$vendorRoot = dirname($resources, 3);
$sourceVersion = 'rinvex/countries';
$installed = $vendorRoot . '/composer/installed.json';

if (is_file($installed)) {
    $data = json_decode((string) file_get_contents($installed), true);

    foreach ((is_array($data) ? ($data['packages'] ?? []) : []) as $package) {
        if (is_array($package) && ($package['name'] ?? '') === 'rinvex/countries') {
            $sourceVersion .= ' ' . (is_string($package['version'] ?? null) ? $package['version'] : 'unknown');

            break;
        }
    }
}

if (! str_contains($sourceVersion, ' ')) {
    // Better to say so than to imply the dataset is pinned to something it is
    // not. `doctor` reports an unversioned dataset as unknown, not as current.
    $sourceVersion .= ' unknown';
}

$export = var_export($countries, true);
$count = count($countries);

$code = <<<PHP
<?php

declare(strict_types=1);

/**
 * GENERATED by tools/build-dataset.php — do not edit by hand.
 *
 * {$count} countries, keyed by ISO 3166-1 alpha-2. Regenerate with
 * `php tools/build-dataset.php`; `--check` gates it in CI.
 *
 * Coordinates are approximate centroids and bounds are [min_lon, min_lat,
 * max_lon, max_lat] in GeoJSON bbox order. Both are null / empty where the
 * source does not carry them.
 */
return {$export};

PHP;

$target = $root . '/resources/data/countries.php';

if ($check) {
    $current = is_file($target) ? (string) file_get_contents($target) : '';

    if ($current === $code) {
        fwrite(STDOUT, "Dataset is in sync ({$count} countries).\n");

        exit(0);
    }

    fwrite(STDERR, "resources/data/countries.php does not match what tools/build-dataset.php produces.\n\n"
        . "Either the file was edited by hand, or the source data moved. Run the generator and read the\n"
        . "diff before committing it.\n");

    exit(1);
}

file_put_contents($target, $code);
file_put_contents($root . '/resources/data/dataset-version.txt', $sourceVersion . "\n");

fwrite(STDOUT, "{$count} countries written from {$sourceVersion}.\n");
