<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Atlas\Core\Network;

use Stringable;

/**
 * A parsed IP address, with its family and its reserved-range classification.
 *
 * ## Why the classification is here and not hand-rolled per caller
 *
 * `isPrivate()` is what an SSRF guard asks before following a URL, so getting a
 * range boundary wrong is a security bug rather than a tidiness one. The org's
 * existing validator declares RFC 1918's middle block as
 * `172.16.0.0`–`172.16.255.255`. That block is a slash-twelve and ends at
 * `172.31.255.255`, so fifteen of its sixteen sub-blocks were classified
 * public — and `172.17.0.0/16` is Docker's default bridge network, which means
 * a containerised application's own internal range read as a public address.
 *
 * The ranges below are therefore written as prefix lengths and expanded
 * arithmetically, not typed out as pairs — the arithmetic cannot disagree with
 * the prefix the way a hand-written pair can.
 *
 * Parsing is `inet_pton` only. Hand-rolled octet splitting accepts `01.02.03.04`,
 * which some resolvers read as octal, and that difference is exactly how a
 * blocklist gets bypassed.
 */
final readonly class IpAddress implements Stringable
{
    /**
     * IPv4 ranges that are not globally routable, as [network, prefix length].
     *
     * @var list<array{string, int}>
     */
    private const array V4_RESERVED = [
        ['0.0.0.0', 8],          // "this network" (RFC 1122)
        ['10.0.0.0', 8],         // private (RFC 1918)
        ['100.64.0.0', 10],      // carrier-grade NAT (RFC 6598)
        ['127.0.0.0', 8],        // loopback
        ['169.254.0.0', 16],     // link-local (RFC 3927)
        ['172.16.0.0', 12],      // private — a /12, NOT a /16. Ends at 172.31.255.255.
        ['192.0.0.0', 24],       // IETF protocol assignments
        ['192.0.2.0', 24],       // TEST-NET-1
        ['192.168.0.0', 16],     // private (RFC 1918)
        ['198.18.0.0', 15],      // benchmarking (RFC 2544)
        ['198.51.100.0', 24],    // TEST-NET-2
        ['203.0.113.0', 24],     // TEST-NET-3
        ['224.0.0.0', 4],        // multicast
        ['240.0.0.0', 4],        // reserved, includes 255.255.255.255
    ];

    /**
     * IPv6 ranges that are not globally routable.
     *
     * @var list<array{string, int}>
     */
    private const array V6_RESERVED = [
        ['::', 128],             // unspecified
        ['::1', 128],            // loopback
        ['::ffff:0:0', 96],      // IPv4-mapped — carries a v4 address, judged as one
        ['64:ff9b::', 96],       // NAT64
        ['100::', 64],           // discard-only
        ['2001:db8::', 32],      // documentation
        ['fc00::', 7],           // unique local (RFC 4193)
        ['fe80::', 10],          // link-local
        ['ff00::', 8],           // multicast
    ];

    private function __construct(
        public string $address,
        public AddressFamily $family,
        /** The `inet_pton` form: 4 bytes for IPv4, 16 for IPv6. */
        public string $packed,
    ) {}

    public function __toString(): string
    {
        return $this->address;
    }

    /**
     * Parse an address, or null if it is not one.
     *
     * Null rather than an exception: this is asked of user input, and "is that
     * an IP" is a question, not an error.
     */
    public static function parse(string $value): ?self
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        // filter_var, not a split on '.'. Hand-rolled parsing accepts
        // 01.02.03.04, which some resolvers read as octal — 010 is 8 — so a
        // blocklist checking the literal string and a resolver reading the
        // address can disagree about where it points.
        if (filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            $packed = inet_pton($value);

            return $packed === false ? null : new self($value, AddressFamily::V4, $packed);
        }

        if (filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
            $packed = inet_pton($value);

            return $packed === false ? null : new self($value, AddressFamily::V6, $packed);
        }

        return null;
    }

    public function isV4(): bool
    {
        return $this->family === AddressFamily::V4;
    }

    public function isV6(): bool
    {
        return $this->family === AddressFamily::V6;
    }

    /**
     * Whether this address is globally routable.
     *
     * The inverse of {@see isReserved()}, named for what a caller usually wants
     * to ask.
     */
    public function isPublic(): bool
    {
        return ! $this->isReserved();
    }

    /**
     * Whether the address falls in any range that is not globally routable —
     * private, loopback, link-local, multicast, documentation or reserved.
     *
     * An IPv4-mapped IPv6 address (`::ffff:1.2.3.4`) is judged by the IPv4
     * address it carries, because that is where a packet to it actually goes.
     * Treating the mapping as "some v6 address, therefore public" is a
     * documented way past an SSRF filter.
     */
    public function isReserved(): bool
    {
        if ($this->isV4()) {
            return $this->matchesAny(self::V4_RESERVED);
        }

        $mapped = $this->mappedV4();

        if ($mapped instanceof self) {
            return $mapped->isReserved();
        }

        return $this->matchesAny(self::V6_RESERVED);
    }

    public function isLoopback(): bool
    {
        return $this->isV4()
            ? $this->matchesAny([['127.0.0.0', 8]])
            : ($this->mappedV4()?->isLoopback() ?? $this->matchesAny([['::1', 128]]));
    }

    /**
     * Specifically RFC 1918 / RFC 4193 private space, as distinct from every
     * other kind of non-routable.
     *
     * Kept apart from {@see isReserved()} because they answer different
     * questions: a multicast address is not routable and is also not "on my
     * network", and a guard that conflates the two reports the wrong reason.
     */
    public function isPrivate(): bool
    {
        if ($this->isV4()) {
            return $this->matchesAny([
                ['10.0.0.0', 8],
                ['172.16.0.0', 12],
                ['192.168.0.0', 16],
            ]);
        }

        return $this->mappedV4()?->isPrivate() ?? $this->matchesAny([['fc00::', 7]]);
    }

    /**
     * The IPv4 address inside an IPv4-mapped IPv6 address, or null.
     */
    public function mappedV4(): ?self
    {
        if (! $this->isV6()) {
            return null;
        }

        $prefix = inet_pton('::ffff:0:0');

        if ($prefix === false || ! str_starts_with($this->packed, substr($prefix, 0, 12))) {
            return null;
        }

        $v4 = inet_ntop(substr($this->packed, 12));

        return $v4 === false ? null : self::parse($v4);
    }

    /**
     * @param list<array{string, int}> $ranges
     */
    private function matchesAny(array $ranges): bool
    {
        foreach ($ranges as [$network, $prefix]) {
            if ($this->inPrefix($network, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether this address falls inside `network/prefix`.
     *
     * Compares whole bytes with `substr` and the remainder with a mask, on the
     * packed form. Doing it on the packed bytes rather than on integers is what
     * lets one implementation serve both families — PHP has no unsigned 128-bit
     * integer, so IPv6 cannot be done arithmetically without GMP.
     */
    private function inPrefix(string $network, int $prefix): bool
    {
        $packedNetwork = inet_pton($network);

        if ($packedNetwork === false || strlen($packedNetwork) !== strlen($this->packed)) {
            return false;
        }

        $wholeBytes = intdiv($prefix, 8);
        $remainingBits = $prefix % 8;

        if ($wholeBytes > 0 && substr($this->packed, 0, $wholeBytes) !== substr($packedNetwork, 0, $wholeBytes)) {
            return false;
        }

        if ($remainingBits === 0) {
            return true;
        }

        $mask = 0xFF << (8 - $remainingBits) & 0xFF;

        return (ord($this->packed[$wholeBytes]) & $mask) === (ord($packedNetwork[$wholeBytes]) & $mask);
    }
}
