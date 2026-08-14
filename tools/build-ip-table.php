<?php

declare(strict_types=1);

/**
 * Builds `resources/data/ip-ipv4.php` and `ip-ipv6.php` from the regional
 * registries' delegated-extended files.
 *
 *   php tools/build-ip-table.php              fetch and write
 *   php tools/build-ip-table.php --from=DIR   build from files already on disk
 *
 * ## Why this is not run for you, and its output is not committed
 *
 * The five source files total roughly 10 MB and change **daily** — every
 * allocation, transfer and return the registries record. A snapshot committed to
 * this repository is stale the day after it is written, and nobody would think
 * to regenerate it. It would also make every consumer carry a table most of them
 * never ask for.
 *
 * So `laranail.atlas.ip.enabled` is off by default, and an application that
 * wants offline IP-to-country runs this on whatever schedule suits it. That is
 * the honest trade: this is registry data with a refresh cadence, not a
 * catalogue that changes when a country is renamed.
 *
 * ## What this data can and cannot answer
 *
 * Delegation files record which registry allocated which block to which
 * **country**, and that is all. There is no city, no ISP name, no VPN or proxy
 * flag — those are not in the source and cannot be derived from it. Anything
 * claiming otherwise from this data is guessing.
 *
 * ## Format
 *
 * Each line is `registry|cc|type|start|value|date|status[|extensions]`. For
 * `ipv4`, `value` is a **count of addresses**, not a prefix length — and that
 * count is frequently not a power of two, because a delegation of 1,536
 * addresses is three CIDR blocks recorded as one row. For `ipv6`, `value` *is*
 * the prefix length. Reading one as the other is the classic way to build a
 * table that is subtly wrong everywhere.
 */
$root = dirname(__DIR__);

$sources = [
    'afrinic' => 'https://ftp.afrinic.net/pub/stats/afrinic/delegated-afrinic-extended-latest',
    'apnic' => 'https://ftp.apnic.net/stats/apnic/delegated-apnic-extended-latest',
    'arin' => 'https://ftp.arin.net/pub/stats/arin/delegated-arin-extended-latest',
    'lacnic' => 'https://ftp.lacnic.net/pub/stats/lacnic/delegated-lacnic-extended-latest',
    'ripencc' => 'https://ftp.ripe.net/pub/stats/ripencc/delegated-ripencc-extended-latest',
];

$fromDirectory = null;

foreach ($argv as $argument) {
    if (str_starts_with((string) $argument, '--from=')) {
        $fromDirectory = substr((string) $argument, 7);
    }
}

/**
 * @return list<string>
 */
$readSource = static function (string $registry, string $url) use ($fromDirectory): array {
    if ($fromDirectory !== null) {
        $file = rtrim($fromDirectory, '/') . '/delegated-' . $registry . '-extended-latest';

        if (! is_file($file)) {
            fwrite(STDERR, "Missing local source: {$file}\n");

            exit(1);
        }

        $body = (string) file_get_contents($file);
    } else {
        fwrite(STDOUT, "  fetching {$registry}…\n");
        $body = @file_get_contents($url, false, stream_context_create([
            'http' => ['timeout' => 120, 'user_agent' => 'laranail-atlas/build-ip-table'],
        ]) ?: null);

        if ($body === false) {
            fwrite(STDERR, "Could not fetch {$url}.\nRun with --from=DIR to build from files you already have.\n");

            exit(1);
        }
    }

    return explode("\n", $body);
};

$v4 = [];
$v6 = [];
$skipped = 0;

foreach ($sources as $registry => $url) {
    foreach ($readSource($registry, $url) as $line) {
        $line = trim($line);

        // Comments, the version header, and the per-registry summary rows.
        if ($line === '' || str_starts_with($line, '#') || str_contains($line, '|summary')) {
            continue;
        }

        $parts = explode('|', $line);

        if (count($parts) < 7) {
            continue;
        }

        [, $country, $type, $start, $value, , $status] = $parts;

        // `allocated` and `assigned` are held by someone; `available` and
        // `reserved` are not, and mapping them to a country would be inventing
        // an answer for space nobody uses.
        if (! in_array($status, ['allocated', 'assigned'], true)) {
            continue;
        }

        $country = strtoupper(trim($country));

        if (strlen($country) !== 2 || $country === 'ZZ') {
            continue;
        }

        if ($type === 'ipv4') {
            // `value` is a COUNT of addresses, not a prefix length, and it is
            // often not a power of two. Treating it as a prefix here would
            // misplace most of the table.
            $count = (int) $value;
            $first = ip2long($start);

            if ($count < 1 || $first === false) {
                $skipped++;

                continue;
            }

            $last = $first + $count - 1;

            // long2ip wants an int in the unsigned 32-bit range; a delegation
            // that overruns it is malformed, not something to wrap around.
            if ($last > 4294967295) {
                $skipped++;

                continue;
            }

            $v4[] = [long2ip($first), long2ip($last), $country];

            continue;
        }

        if ($type === 'ipv6') {
            // Here `value` IS the prefix length.
            $prefix = (int) $value;
            $packed = @inet_pton($start);

            if ($packed === false || $prefix < 1 || $prefix > 128) {
                $skipped++;

                continue;
            }

            $v6[] = [$start, lastOfPrefix($packed, $prefix), $country];
        }
    }
}

/**
 * The last address in `network/prefix`, as a string.
 *
 * Done on the packed bytes: PHP has no unsigned 128-bit integer, so the
 * arithmetic form of this needs GMP and this does not.
 */
function lastOfPrefix(string $packed, int $prefix): string
{
    $wholeBytes = intdiv($prefix, 8);
    $remainingBits = $prefix % 8;

    $bytes = str_split($packed);

    if ($remainingBits > 0) {
        $mask = 0xFF >> $remainingBits;
        $bytes[$wholeBytes] = chr(ord($bytes[$wholeBytes]) | $mask);
        $wholeBytes++;
    }

    for ($i = $wholeBytes; $i < 16; $i++) {
        $bytes[$i] = "\xFF";
    }

    $result = inet_ntop(implode('', $bytes));

    return $result === false ? '::' : $result;
}

if ($v4 === [] && $v6 === []) {
    fwrite(STDERR, "No ranges were read; refusing to write an empty table.\n");

    exit(1);
}

usort($v4, static fn (array $a, array $b): int => ip2long($a[0]) <=> ip2long($b[0]));
usort($v6, static fn (array $a, array $b): int => strcmp((string) inet_pton($a[0]), (string) inet_pton($b[0])));

$write = static function (string $file, array $rows, string $family) use ($root): void {
    $count = count($rows);
    $export = var_export($rows, true);

    file_put_contents($root . '/resources/data/' . $file, <<<PHP
    <?php

    declare(strict_types=1);

    /**
     * GENERATED by tools/build-ip-table.php — do not edit by hand, and do not commit.
     *
     * {$count} {$family} ranges, as [first, last, country]. Registry delegation data changes daily;
     * regenerate on whatever schedule suits the application.
     */
    return {$export};

    PHP);

    fwrite(STDOUT, sprintf("  %-6s %d ranges\n", $family, $count));
};

$write('ip-ipv4.php', $v4, 'ipv4');
$write('ip-ipv6.php', $v6, 'ipv6');

file_put_contents($root . '/resources/data/ip-version.txt', gmdate('Y-m-d') . "\n");

if ($skipped > 0) {
    fwrite(STDOUT, "  {$skipped} malformed rows skipped\n");
}
