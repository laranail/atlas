<?php

declare(strict_types=1);

/**
 * Runs deptrac and fails on anything that would leave the architecture boundary unenforced.
 *
 * **deptrac does not exit non-zero when it cannot parse a file.** It prints "Syntax Error on File
 * …", then reports "Violations 0 / Errors 0" and exits 0 — so a file it cannot read is a file with
 * no rules applied, and the build goes green. `laranail/chrono` hit exactly this: deptrac 3.0.0
 * could not parse PHP 8.5's `clone ($this, [...])`, and its `src/Core` was unguarded for as long as
 * that went unnoticed.
 *
 * Both packages pin deptrac ^4.7, which parses current syntax. This guard exists because the pin
 * fixes today's instance and not the class: the next language feature deptrac lags on would fail
 * the same silent way.
 *
 * Fails when: deptrac exits non-zero · any violation is reported · any error is reported ·
 * the output mentions a syntax error · the JSON report cannot be read at all.
 */
$root = dirname(__DIR__);
$binary = $root . '/vendor/bin/deptrac';

if (! is_file($binary)) {
    fwrite(STDERR, "deptrac is not installed; run composer install.\n");

    exit(1);
}

$command = sprintf(
    '%s analyse --config-file=%s --no-progress --formatter=json 2>&1',
    escapeshellarg($binary),
    escapeshellarg($root . '/deptrac.yaml'),
);

exec($command, $lines, $exitCode);
$output = implode("\n", $lines);

$fail = static function (string $reason) use ($output): never {
    fwrite(STDERR, "deptrac guard failed: {$reason}\n\n{$output}\n");

    exit(1);
};

if (stripos($output, 'syntax error') !== false) {
    $fail('deptrac could not parse at least one file, so the boundary was not enforced');
}

// The JSON formatter prints the report object; anything else means deptrac aborted.
$decoded = json_decode($output, true);

if (! is_array($decoded) || ! isset($decoded['Report']) || ! is_array($decoded['Report'])) {
    $fail('deptrac produced no readable JSON report');
}

$report = $decoded['Report'];
$violations = (int) ($report['Violations'] ?? 0);
$errors = (int) ($report['Errors'] ?? 0);

foreach (($decoded['files'] ?? []) as $file => $detail) {
    foreach (($detail['messages'] ?? []) as $message) {
        fwrite(STDERR, sprintf("%s:%s  %s\n", $file, $message['line'] ?? '?', $message['message'] ?? ''));
    }
}

if ($violations > 0) {
    $fail("{$violations} architecture violation(s)");
}

if ($errors > 0) {
    $fail("{$errors} error(s)");
}

if ($exitCode !== 0) {
    $fail("deptrac exited with code {$exitCode}");
}

fwrite(STDOUT, "deptrac: boundary clean (0 violations, 0 errors).\n");

exit(0);
