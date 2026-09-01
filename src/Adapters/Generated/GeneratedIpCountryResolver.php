<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Atlas\Adapters\Generated;

use Simtabi\Laranail\Atlas\Core\Contracts\IpCountryResolver;
use Simtabi\Laranail\Atlas\Core\Network\AddressFamily;
use Simtabi\Laranail\Atlas\Core\Network\IpAddress;
use Simtabi\Laranail\Atlas\Core\Network\IpRange;
use Simtabi\Laranail\Atlas\Core\Network\IpRangeTable;

/**
 * Offline IP-to-country, over the range table built by `tools/build-ip-table.php`.
 *
 * ## The table is not shipped
 *
 * It is built from the five regional registries' delegation files, which are
 * roughly 10 MB of source and change daily. Committing a snapshot would put a
 * file in the repository that is stale the day after it is written and that
 * nobody would think to regenerate — and it would make every consumer carry
 * data most of them never ask for. Run the builder, or leave
 * `laranail.atlas.ip.enabled` off, which is the default.
 *
 * ## Loading is lazy and per-family
 *
 * The two tables are separate because the packed forms are 4 and 16 bytes, and
 * `strcmp` across them compares a prefix — wrong, and silent. An application
 * that only ever sees IPv4 never pays for the v6 table.
 */
final class GeneratedIpCountryResolver implements IpCountryResolver
{
    /** @var array<string, IpRangeTable> */
    private array $tables = [];

    public function __construct(
        private readonly string $dataPath,
    ) {}

    public function countryFor(IpAddress $address): ?string
    {
        // A reserved address belongs to no country, and asking the table is
        // both pointless and misleading — 10.0.0.1 is in use on millions of
        // networks in every country there is.
        if ($address->isReserved()) {
            return null;
        }

        return $this->table($address->family)->countryFor($address);
    }

    public function isReady(): bool
    {
        return array_any(AddressFamily::cases(), fn (AddressFamily $family): bool => is_file($this->fileFor($family)));
    }

    /**
     * How many ranges are loaded per family. What `doctor` prints.
     *
     * @return array<string, int>
     */
    public function counts(): array
    {
        $counts = [];

        foreach (AddressFamily::cases() as $family) {
            $counts[$family->value] = $this->table($family)->count();
        }

        return $counts;
    }

    private function table(AddressFamily $family): IpRangeTable
    {
        return $this->tables[$family->value] ??= $this->load($family);
    }

    private function load(AddressFamily $family): IpRangeTable
    {
        $file = $this->fileFor($family);

        if (! is_file($file)) {
            return IpRangeTable::of($family, []);
        }

        /** @var list<array{0: string, 1: string, 2: string}> $rows */
        $rows = require $file;

        return IpRangeTable::of($family, $this->hydrate($rows));
    }

    /**
     * @param  list<array{0: string, 1: string, 2: string}>  $rows
     * @return list<IpRange>
     */
    private function hydrate(array $rows): array
    {
        $ranges = [];

        foreach ($rows as $row) {
            $first = IpAddress::parse($row[0] ?? '');
            $last = IpAddress::parse($row[1] ?? '');

            if (! $first instanceof IpAddress || ! $last instanceof IpAddress) {
                continue;
            }

            $range = IpRange::between($first, $last, $row[2] ?? '');

            // One malformed row is skipped rather than taking the dataset down.
            // Registry files are third-party input and occasionally contain a
            // line nobody anticipated.
            if ($range instanceof IpRange) {
                $ranges[] = $range;
            }
        }

        return $ranges;
    }

    private function fileFor(AddressFamily $family): string
    {
        return $this->dataPath.'/ip-'.$family->value.'.php';
    }
}
