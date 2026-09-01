<?php

declare(strict_types=1);

use Simtabi\Laranail\Atlas\Core\Network\AddressFamily;
use Simtabi\Laranail\Atlas\Core\Network\IpAddress;

it('parses both families', function (string $ip, AddressFamily $family, int $bytes): void {
    $address = IpAddress::parse($ip);

    expect($address?->family)->toBe($family)
        ->and(strlen((string) $address?->packed))->toBe($bytes);
})->with([
    ['8.8.8.8', AddressFamily::V4, 4],
    ['2001:4860:4860::8888', AddressFamily::V6, 16],
    ['::1', AddressFamily::V6, 16],
]);

it('rejects an address that only looks like one', function (string $value): void {
    expect(IpAddress::parse($value))->toBeNull();
})->with([
    'leading zeros' => '01.02.03.04',
    'out of range' => '256.1.1.1',
    'too few octets' => '1.2.3',
    'too many octets' => '1.2.3.4.5',
    'empty' => '',
    'whitespace' => '   ',
    'a word' => 'not-an-ip',
    'a hostname' => 'example.com',
    'a cidr' => '10.0.0.0/8',
]);

it('rejects leading zeros because a resolver may read them as octal', function (): void {
    // Hand-rolled octet splitting accepts 01.02.03.04. Some resolvers read 010
    // as 8, so a blocklist checking the literal string and a resolver reading
    // the address disagree about where it points — which is a documented way
    // past a filter, not a curiosity.
    expect(IpAddress::parse('01.02.03.04'))->toBeNull()
        ->and(IpAddress::parse('1.2.3.4'))->not->toBeNull();
});

// -----------------------------------------------------------------------
// RFC 1918 — the boundary the org's existing validator got wrong
// -----------------------------------------------------------------------

it('treats the whole of 172.16.0.0/12 as private', function (string $ip): void {
    // The bug this exists to not repeat: enekia declares the block as
    // 172.16.0.0-172.16.255.255, so fifteen of its sixteen sub-blocks are
    // classified public — and 172.17.0.0/16 is Docker's default bridge network,
    // so a containerised app's own internal range reads as public.
    expect(IpAddress::parse($ip)?->isPrivate())->toBeTrue();
})->with([
    '172.16.0.0',
    '172.16.255.255',
    '172.17.0.1',      // Docker's default bridge
    '172.20.10.5',
    '172.24.5.5',
    '172.31.255.255',  // the true upper bound
]);

it('does not extend the block past its real bounds', function (string $ip): void {
    expect(IpAddress::parse($ip)?->isPrivate())->toBeFalse();
})->with(['172.15.255.255', '172.32.0.1']);

it('recognises the other two RFC 1918 blocks', function (string $ip): void {
    expect(IpAddress::parse($ip)?->isPrivate())->toBeTrue();
})->with(['10.0.0.1', '10.255.255.255', '192.168.0.1', '192.168.255.255']);

it('calls a routable address public', function (string $ip): void {
    expect(IpAddress::parse($ip)?->isPublic())->toBeTrue();
})->with(['8.8.8.8', '1.1.1.1', '2001:4860:4860::8888']);

// -----------------------------------------------------------------------
// Everything else that is not routable
// -----------------------------------------------------------------------

it('separates private from merely non-routable', function (): void {
    // A multicast address is not routable and is also not "on my network".
    // A guard that conflates the two reports the wrong reason.
    $multicast = IpAddress::parse('224.0.0.1');

    expect($multicast?->isReserved())->toBeTrue()
        ->and($multicast?->isPrivate())->toBeFalse();
});

it('classifies the reserved ranges', function (string $ip): void {
    expect(IpAddress::parse($ip)?->isReserved())->toBeTrue()
        ->and(IpAddress::parse($ip)?->isPublic())->toBeFalse();
})->with([
    'loopback' => '127.0.0.1',
    'this network' => '0.0.0.0',
    'link-local' => '169.254.1.1',
    'carrier-grade NAT' => '100.64.0.1',
    'TEST-NET-1' => '192.0.2.1',
    'benchmarking' => '198.19.0.1',
    'multicast' => '224.0.0.1',
    'broadcast' => '255.255.255.255',
    'v6 loopback' => '::1',
    'v6 unspecified' => '::',
    'v6 unique local' => 'fd00::1',
    'v6 link-local' => 'fe80::1',
    'v6 documentation' => '2001:db8::1',
    'v6 multicast' => 'ff02::1',
]);

it('judges an IPv4-mapped address by the address it carries', function (): void {
    // ::ffff:127.0.0.1 goes to 127.0.0.1. Treating the mapping as "a v6
    // address, therefore public" is a documented way past an SSRF filter.
    $mapped = IpAddress::parse('::ffff:127.0.0.1');

    expect($mapped?->isV6())->toBeTrue()
        ->and($mapped?->isLoopback())->toBeTrue()
        ->and($mapped?->isReserved())->toBeTrue()
        ->and((string) $mapped?->mappedV4())->toBe('127.0.0.1');
});

it('judges a mapped private address as private', function (): void {
    expect(IpAddress::parse('::ffff:172.17.0.1')?->isPrivate())->toBeTrue();
});

it('leaves a genuine v6 address unmapped', function (): void {
    expect(IpAddress::parse('2001:4860:4860::8888')?->mappedV4())->toBeNull()
        ->and(IpAddress::parse('8.8.8.8')?->mappedV4())->toBeNull();
});

it('is stringable back to what it parsed', function (): void {
    expect((string) IpAddress::parse('8.8.8.8'))->toBe('8.8.8.8');
});
