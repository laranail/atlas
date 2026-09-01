<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Atlas\Core\Network;

/**
 * A sorted, non-overlapping set of {@see IpRange}s, searched by bisection.
 *
 * Registry allocation data runs to roughly 200,000 IPv4 ranges. A linear scan
 * averages 100,000 comparisons per lookup; bisection is 18. That is the whole
 * reason this class exists rather than a `foreach`.
 *
 * Bisection is only correct on sorted, non-overlapping input, so the constructor
 * establishes both rather than assuming them — sorting is cheap and done once,
 * and an overlap is dropped with the later range losing, which is the
 * conservative choice when two registry rows disagree.
 *
 * Families are held in separate tables. Their packed forms are 4 and 16 bytes,
 * and `strcmp` across the two compares a prefix — wrong, and silent.
 */
final readonly class IpRangeTable
{
    /**
     * @param  list<IpRange>  $ranges  sorted by `first`, non-overlapping
     */
    private function __construct(
        public AddressFamily $family,
        private array $ranges,
    ) {}

    /**
     * @param  iterable<IpRange>  $ranges  in any order
     */
    public static function of(AddressFamily $family, iterable $ranges): self
    {
        $accepted = [];

        foreach ($ranges as $range) {
            if ($range->family === $family) {
                $accepted[] = $range;
            }
        }

        usort($accepted, static fn (IpRange $a, IpRange $b): int => strcmp($a->first, $b->first));

        // Drop anything that overlaps the range before it. Bisection assumes
        // non-overlap, and a table that quietly breaks that assumption returns
        // wrong answers rather than obviously failing.
        $clean = [];
        $previousLast = null;

        foreach ($accepted as $range) {
            if ($previousLast !== null && strcmp($range->first, $previousLast) <= 0) {
                continue;
            }

            $clean[] = $range;
            $previousLast = $range->last;
        }

        return new self($family, $clean);
    }

    public function count(): int
    {
        return count($this->ranges);
    }

    public function isEmpty(): bool
    {
        return $this->ranges === [];
    }

    /**
     * The ISO 3166-1 alpha-2 code allocated this address, or null.
     *
     * Null means "not in the table", which is not the same as "not a country" —
     * registry data has gaps, and a caller that needs to tell the two apart
     * should check {@see isEmpty()} first.
     */
    public function countryFor(IpAddress $address): ?string
    {
        return $this->find($address)?->country;
    }

    public function find(IpAddress $address): ?IpRange
    {
        if ($address->family !== $this->family || $this->ranges === []) {
            return null;
        }

        $low = 0;
        $high = count($this->ranges) - 1;

        while ($low <= $high) {
            // (low + high) >>> 1 rather than (low + high) / 2 would matter for
            // arrays past 2^62 entries. It does not here, and intdiv is clearer.
            $mid = intdiv($low + $high, 2);
            $range = $this->ranges[$mid];

            if (strcmp($address->packed, $range->first) < 0) {
                $high = $mid - 1;

                continue;
            }

            if (strcmp($address->packed, $range->last) > 0) {
                $low = $mid + 1;

                continue;
            }

            return $range;
        }

        return null;
    }
}
