<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Atlas\Core\Contracts;

use Simtabi\Laranail\Atlas\Core\Network\IpAddress;

/**
 * Which country an address was allocated to.
 *
 * **Country only, and offline only.** Regional-registry delegation data is
 * authoritative for allocation, freely redistributable and updated daily, which
 * is exactly enough to answer this and nothing else. City, ISP name and
 * VPN/proxy status are not in it and cannot be derived from it — those need a
 * commercial feed, which is `laranail/ip-intel`'s problem, not this package's.
 *
 * Stating the limit here rather than shipping a `city()` that returns null:
 * a method that always answers null reads as a bug in the data, and this is a
 * property of what the data is.
 */
interface IpCountryResolver
{
    /**
     * The ISO 3166-1 alpha-2 code, or null.
     *
     * Null covers three different things, deliberately not distinguished at
     * this level: the address is reserved and belongs to no country, the
     * registries have a gap, or the dataset is not installed. {@see isReady()}
     * separates the last from the first two.
     */
    public function countryFor(IpAddress $address): ?string;

    /**
     * Whether a dataset is loaded at all.
     *
     * What `doctor` asks. Without it, an uninstalled dataset and an unallocated
     * address are the same null, and the first is a deployment problem while the
     * second is just how the internet is.
     */
    public function isReady(): bool;
}
