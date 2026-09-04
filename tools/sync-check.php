<?php

declare(strict_types=1);

/**
 * CI gate: fails when a generated file disagrees with the source it was generated from.
 *
 * Runs every generator in check mode and compares byte for byte. A stale dataset — or one edited by
 * hand — cannot ship unnoticed.
 *
 * Unlike chrono's equivalent this does not need the source release pinned on the runner. The dataset
 * is generated from `rinvex/countries`, which is a composer dependency at an exact resolved version
 * rather than a database baked into the PHP build, so "the same input" is something composer already
 * guarantees. The check is skipped, not failed, when the source package is absent — that is the
 * ordinary state for a consumer, and it is the CI job that installs it.
 */
$root = dirname(__DIR__);

$generators = [
    'countries' => [
        'script'   => __DIR__ . '/build-dataset.php',
        'requires' => $root . '/vendor/rinvex/countries/resources/data',
    ],
    // Generated from the committed dataset rather than from the source package,
    // so this runs everywhere — there is nothing optional to be missing.
    'enums' => [
        'script'   => __DIR__ . '/generate-enums.php',
        'requires' => $root . '/resources/data',
    ],
];

$failed = [];
$skipped = [];

foreach ($generators as $label => $generator) {
    if (! is_dir($generator['requires'])) {
        $skipped[$label] = $generator['requires'];

        continue;
    }

    $output = [];
    $exitCode = 0;

    exec(
        sprintf('%s %s --check 2>&1', escapeshellarg(PHP_BINARY), escapeshellarg($generator['script'])),
        $output,
        $exitCode,
    );

    if ($exitCode !== 0) {
        $failed[$label] = implode("\n", $output);

        continue;
    }

    fwrite(STDOUT, sprintf("  %-12s in sync\n", $label));
}

foreach ($skipped as $label => $path) {
    fwrite(STDOUT, sprintf(
        "  %-12s skipped — the source data is not installed (%s).\n"
        . "%sRun `composer require --dev rinvex/countries` to check it here too; CI does.\n",
        $label,
        $path,
        str_repeat(' ', 16),
    ));
}

if ($failed !== []) {
    fwrite(STDERR, "\nGenerated data is stale.\n\n");

    foreach ($failed as $label => $output) {
        fwrite(STDERR, "  {$label}:\n" . preg_replace('/^/m', '    ', $output) . "\n");
    }

    fwrite(STDERR, "\nRun the generator and commit the result.\n");

    exit(1);
}

if ($skipped !== [] && $failed === []) {
    exit(0);
}

fwrite(STDOUT, "\nAll generated data is in sync.\n");

exit(0);
