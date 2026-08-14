<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Atlas\Core\Network;

/**
 * Which IP version an address belongs to.
 *
 * Load-bearing rather than descriptive: the packed forms are 4 and 16 bytes, and
 * comparing one against the other with `strcmp` compares a prefix, which is
 * always wrong and never throws. Every range table is keyed by family for that
 * reason.
 */
enum AddressFamily: string
{
    case V4 = 'ipv4';
    case V6 = 'ipv6';

    /**
     * The length of this family's `inet_pton` form, in bytes.
     */
    public function packedLength(): int
    {
        return match ($this) {
            self::V4 => 4,
            self::V6 => 16,
        };
    }
}
