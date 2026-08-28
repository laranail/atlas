<?php

declare(strict_types=1);

use Simtabi\Laranail\Atlas\Core\Network\IpRange;
use Simtabi\Laranail\Atlas\Core\Network\IpAddress;
use Simtabi\Laranail\Atlas\Core\Network\IpRangeTable;
use Simtabi\Laranail\Atlas\Core\Network\AddressFamily;

function atlasRange(string $first, string $last, string $country): IpRange
{
    $range = IpRange::between(
        IpAddress::parse($first) ?? throw new RuntimeException("bad first: {$first}"),
        IpAddress::parse($last) ?? throw new RuntimeException("bad last: {$last}"),
        $country,
    );

    return $range ?? throw new RuntimeException("bad range: {$first}-{$last}");
}

function atlasIp(string $ip): IpAddress
{
    return IpAddress::parse($ip) ?? throw new RuntimeException("bad ip: {$ip}");
}

// -----------------------------------------------------------------------
// IpRange
// -----------------------------------------------------------------------

it('contains its own bounds', function (): void {
    $range = atlasRange('1.0.0.0', '1.0.0.255', 'KE');

    expect($range->contains(atlasIp('1.0.0.0')))->toBeTrue()
        ->and($range->contains(atlasIp('1.0.0.255')))->toBeTrue()
        ->and($range->contains(atlasIp('1.0.0.128')))->toBeTrue()
        ->and($range->contains(atlasIp('1.0.1.0')))->toBeFalse()
        ->and($range->contains(atlasIp('0.255.255.255')))->toBeFalse();
});

it('never matches an address of the other family', function (): void {
    // The packed forms are 4 and 16 bytes, and strcmp across them compares a
    // prefix — always wrong, and it never throws.
    $v4 = atlasRange('0.0.0.0', '255.255.255.255', 'KE');

    expect($v4->contains(atlasIp('::1')))->toBeFalse()
        ->and($v4->contains(atlasIp('2001:db8::1')))->toBeFalse();
});

it('refuses a range whose bounds are the wrong way round', function (): void {
    expect(IpRange::between(atlasIp('1.0.0.255'), atlasIp('1.0.0.0'), 'KE'))->toBeNull();
});

it('refuses a range spanning two families', function (): void {
    expect(IpRange::between(atlasIp('1.0.0.0'), atlasIp('::1'), 'KE'))->toBeNull();
});

it('refuses a country code that is not one', function (string $code): void {
    expect(IpRange::between(atlasIp('1.0.0.0'), atlasIp('1.0.0.255'), $code))->toBeNull();
})->with(['', 'K', 'KEN', 'KENYA']);

it('normalises the country code', function (): void {
    expect(atlasRange('1.0.0.0', '1.0.0.255', ' ke ')->country)->toBe('KE');
});

it('orders v6 correctly across the high bit', function (): void {
    // The load-bearing claim: inet_pton output is big-endian and strcmp is
    // unsigned, so lexicographic order equals numeric order. If strcmp were
    // signed on bytes, ::1 would sort *above* 8000:: and this range would
    // contain nothing.
    $range = atlasRange('::', 'ffff:ffff:ffff:ffff:ffff:ffff:ffff:ffff', 'KE');

    expect($range->contains(atlasIp('::1')))->toBeTrue()
        ->and($range->contains(atlasIp('8000::')))->toBeTrue()
        ->and($range->contains(atlasIp('ffff::')))->toBeTrue();
});

it('handles a v6 range whose packed form contains NUL bytes', function (): void {
    // 2001:db8:: is full of them. A comparison that stopped at the first NUL
    // would treat most of the address space as one value.
    $range = atlasRange('2001:db8::', '2001:db8:0:0:ffff:ffff:ffff:ffff', 'KE');

    expect($range->contains(atlasIp('2001:db8::5')))->toBeTrue()
        ->and($range->contains(atlasIp('2001:db8:0:1::1')))->toBeFalse()
        ->and($range->contains(atlasIp('2001:db7:ffff::')))->toBeFalse();
});

// -----------------------------------------------------------------------
// IpRangeTable
// -----------------------------------------------------------------------

it('finds the country for an address', function (): void {
    $table = IpRangeTable::of(AddressFamily::V4, [
        atlasRange('1.0.0.0', '1.0.0.255', 'AU'),
        atlasRange('2.0.0.0', '2.0.0.255', 'FR'),
        atlasRange('3.0.0.0', '3.0.0.255', 'US'),
    ]);

    expect($table->countryFor(atlasIp('2.0.0.7')))->toBe('FR')
        ->and($table->countryFor(atlasIp('1.0.0.0')))->toBe('AU')
        ->and($table->countryFor(atlasIp('3.0.0.255')))->toBe('US');
});

it('returns null for an address in no range', function (): void {
    $table = IpRangeTable::of(AddressFamily::V4, [
        atlasRange('1.0.0.0', '1.0.0.255', 'AU'),
        atlasRange('3.0.0.0', '3.0.0.255', 'US'),
    ]);

    // Between two ranges, before the first, and after the last — the three
    // places a bisection loop most often walks off the end.
    expect($table->countryFor(atlasIp('2.0.0.1')))->toBeNull()
        ->and($table->countryFor(atlasIp('0.255.255.255')))->toBeNull()
        ->and($table->countryFor(atlasIp('4.0.0.0')))->toBeNull();
});

it('sorts the input rather than trusting it', function (): void {
    // Bisection is only correct on sorted input, and a table built from
    // unsorted rows would return wrong answers rather than obviously failing.
    $table = IpRangeTable::of(AddressFamily::V4, [
        atlasRange('3.0.0.0', '3.0.0.255', 'US'),
        atlasRange('1.0.0.0', '1.0.0.255', 'AU'),
        atlasRange('2.0.0.0', '2.0.0.255', 'FR'),
    ]);

    expect($table->countryFor(atlasIp('1.0.0.1')))->toBe('AU')
        ->and($table->countryFor(atlasIp('2.0.0.1')))->toBe('FR')
        ->and($table->countryFor(atlasIp('3.0.0.1')))->toBe('US');
});

it('drops a range that overlaps the one before it', function (): void {
    // Two registry rows disagreeing is the case; keeping the earlier one is the
    // conservative choice, and keeping both would break bisection's assumption.
    $table = IpRangeTable::of(AddressFamily::V4, [
        atlasRange('1.0.0.0', '1.0.0.255', 'AU'),
        atlasRange('1.0.0.128', '1.0.1.255', 'NZ'),
    ]);

    expect($table->count())->toBe(1)
        ->and($table->countryFor(atlasIp('1.0.0.200')))->toBe('AU');
});

it('ignores ranges of the other family', function (): void {
    $table = IpRangeTable::of(AddressFamily::V4, [
        atlasRange('1.0.0.0', '1.0.0.255', 'AU'),
        atlasRange('2001:db8::', '2001:db8::ffff', 'FR'),
    ]);

    expect($table->count())->toBe(1)
        ->and($table->countryFor(atlasIp('2001:db8::1')))->toBeNull();
});

it('answers an empty table without walking off it', function (): void {
    $table = IpRangeTable::of(AddressFamily::V4, []);

    expect($table->isEmpty())->toBeTrue()
        ->and($table->count())->toBe(0)
        ->and($table->countryFor(atlasIp('8.8.8.8')))->toBeNull();
});

it('finds every address in a table large enough for bisection to matter', function (): void {
    // A linear scan and a bisection agree on three ranges. This is where they
    // stop agreeing if the loop bounds are off by one.
    $ranges = [];

    for ($i = 0; $i < 500; $i++) {
        $ranges[] = atlasRange(
            long2ip($i * 512),
            long2ip($i * 512 + 255),
            $i % 2 === 0 ? 'AU' : 'FR',
        );
    }

    $table = IpRangeTable::of(AddressFamily::V4, $ranges);

    expect($table->count())->toBe(500);

    for ($i = 0; $i < 500; $i++) {
        $inside = atlasIp(long2ip($i * 512 + 128));
        $gap = atlasIp(long2ip($i * 512 + 400));

        expect($table->countryFor($inside))->toBe($i % 2 === 0 ? 'AU' : 'FR')
            ->and($table->countryFor($gap))->toBeNull();
    }
});

it('bisects a v6 table correctly', function (): void {
    $table = IpRangeTable::of(AddressFamily::V6, [
        atlasRange('2001:200::', '2001:200:ffff:ffff:ffff:ffff:ffff:ffff', 'JP'),
        atlasRange('2001:400::', '2001:400:ffff:ffff:ffff:ffff:ffff:ffff', 'US'),
        atlasRange('2c0f::', '2c0f:ffff:ffff:ffff:ffff:ffff:ffff:ffff', 'KE'),
    ]);

    expect($table->countryFor(atlasIp('2001:200::1')))->toBe('JP')
        ->and($table->countryFor(atlasIp('2001:400:1::')))->toBe('US')
        ->and($table->countryFor(atlasIp('2c0f:f000::1')))->toBe('KE')
        ->and($table->countryFor(atlasIp('2001:300::1')))->toBeNull();
});
