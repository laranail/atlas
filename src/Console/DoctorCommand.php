<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Atlas\Console;

use Simtabi\Laranail\Atlas\Services\AtlasService;
use Simtabi\Laranail\Console\Tools\Commands\Command;
use Simtabi\Laranail\Console\Tools\Commands\Concerns\SupportsNamespacedNames;

/**
 * "Is this installation's catalogue trustworthy?"
 *
 * Three questions, each of which fails silently otherwise:
 *
 * 1. **Is a data source answering at all?** A configured provider whose data
 *    package is not installed returns an empty catalogue, and an empty
 *    catalogue makes every country look nonexistent rather than raising
 *    anything.
 * 2. **How old is the data?** Countries change — codes are reassigned, names
 *    change, currencies are replaced. A dataset nobody has regenerated in two
 *    years is wrong in ways no test will catch.
 * 3. **Is the IP table installed?** It is built rather than shipped, so
 *    `countryForIp()` returns null on a fresh install — which is
 *    indistinguishable from "this address is not allocated" unless something
 *    says so.
 *
 * No generic alias. `atlas:doctor` would claim a name any package or
 * application could also want, and Artisan's registry is a flat map where the
 * loser is replaced without a word.
 */
final class DoctorCommand extends Command
{
    use SupportsNamespacedNames;

    // No #[Override] on these two. The attribute targets methods only, and PHP
    // does not complain when it is parsed, only when something reflects the
    // class, so it passed locally and failed the moment CI's test run walked
    // the command's attributes.
    protected $signature = 'laranail::atlas.doctor {--strict : Treat warnings as failures}';

    protected $description = 'Check the country catalogue, its age, and the IP table.';

    public function handle(AtlasService $atlas): int
    {
        $report = $atlas->describe();
        $failed = false;
        $warned = false;

        $this->components->info('Data source');

        if ($report['available'] === false) {
            $this->components->error(sprintf(
                'The configured source (%s) is not available. Its data package is probably not installed.',
                $report['provider'],
            ));
            $failed = true;
        } else {
            $this->components->twoColumnDetail('Provider', $report['provider']);
            $this->components->twoColumnDetail('Countries', (string) $report['countries']);
        }

        $version = $report['version'];

        if ($version === null) {
            // Unknown, not current. A source that cannot date itself cannot be
            // checked for staleness, and reporting it as fine would be a
            // guess dressed as a result.
            $this->components->warn('The source does not report a version, so its age cannot be checked.');
            $warned = true;
        } else {
            $this->components->twoColumnDetail('Dataset', $version);

            if ($this->isStale($version)) {
                $this->components->warn(sprintf(
                    'The dataset is dated %s. Regenerate it with tools/build-dataset.php.',
                    $version,
                ));
                $warned = true;
            }
        }

        $this->components->info('IP to country');

        if ($report['ip_ready']) {
            $this->components->twoColumnDetail('Range table', 'installed');
        } else {
            // A warning, not a failure: an application using atlas for its
            // country catalogue and nothing else is entitled to skip a table
            // built from five registry downloads.
            $this->components->warn(
                'No range table, so countryForIp() answers null for every address. '
                . 'Build one with tools/build-ip-table.php if you need it.',
            );
            $warned = true;
        }

        $this->components->info('Distance');
        $this->components->twoColumnDetail('Formula', $report['distance']);

        if ($failed) {
            return self::FAILURE;
        }

        return $warned && $this->option('strict') ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Older than a year.
     *
     * A year rather than a month because the ISO registers move slowly — the
     * threshold exists to catch a dataset nobody has thought about since the
     * package was installed, not to nag about a fortnight.
     */
    private function isStale(string $version): bool
    {
        $stamp = strtotime($version);

        return $stamp !== false && $stamp < strtotime('-1 year');
    }
}
