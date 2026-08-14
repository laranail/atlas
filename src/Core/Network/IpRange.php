<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Atlas\Core\Network;

/**
 * A contiguous span of addresses allocated to one country.
 *
 * Stored as packed first/last bounds rather than as network/prefix, because
 * registry allocations are frequently not on a prefix boundary — a delegation
 * of 1,536 addresses is three CIDR blocks, and keeping it as one range is both
 * smaller and cheaper to search.
 */
final readonly class IpRange
{
    private function __construct(
        public AddressFamily $family,
        /** Packed `inet_pton` form of the first address, inclusive. */
        public string $first,
        /** Packed form of the last address, inclusive. */
        public string $last,
        /** ISO 3166-1 alpha-2, upper case. */
        public string $country,
    ) {}

    /**
     * Build from two addresses of the same family.
     *
     * Returns null rather than throwing on mismatch: this reads a generated
     * table, and one malformed row should be skipped rather than take the whole
     * dataset down.
     */
    public static function between(IpAddress $first, IpAddress $last, string $country): ?self
    {
        if ($first->family !== $last->family) {
            return null;
        }

        // strcmp is the right comparison on packed bytes — it is unsigned and
        // NUL-safe, and inet_pton output is big-endian, so lexicographic order
        // equals numeric order. Verified for the high-bit case: ::1 sorts below
        // 8000:: rather than above it.
        if (strcmp($first->packed, $last->packed) > 0) {
            return null;
        }

        $country = strtoupper(trim($country));

        if (strlen($country) !== 2) {
            return null;
        }

        return new self($first->family, $first->packed, $last->packed, $country);
    }

    public function contains(IpAddress $address): bool
    {
        // Family first. The packed forms are 4 and 16 bytes, and strcmp across
        // the two compares a prefix — always wrong, never an error.
        if ($address->family !== $this->family) {
            return false;
        }

        return strcmp($address->packed, $this->first) >= 0
            && strcmp($address->packed, $this->last) <= 0;
    }
}
