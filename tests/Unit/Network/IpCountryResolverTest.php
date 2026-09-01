<?php

declare(strict_types=1);

use Simtabi\Laranail\Atlas\Adapters\Generated\GeneratedIpCountryResolver;
use Simtabi\Laranail\Atlas\Core\Network\IpAddress;

/**
 * The resolver against a table written for the test, because the real one is
 * built from ~10 MB of registry data that changes daily and is deliberately not
 * committed — see `tools/build-ip-table.php`.
 */
function atlasIpSandbox(array $v4 = [], array $v6 = []): string
{
    $dir = sys_get_temp_dir().'/atlas-ip-'.bin2hex(random_bytes(6));
    mkdir($dir, 0o777, true);

    if ($v4 !== []) {
        file_put_contents($dir.'/ip-ipv4.php', '<?php return '.var_export($v4, true).';');
    }

    if ($v6 !== []) {
        file_put_contents($dir.'/ip-ipv6.php', '<?php return '.var_export($v6, true).';');
    }

    return $dir;
}

afterEach(function (): void {
    foreach (glob(sys_get_temp_dir().'/atlas-ip-*') ?: [] as $dir) {
        exec('rm -rf '.escapeshellarg($dir));
    }
});

function atlasResolve(string $ip, string $dir): ?string
{
    return new GeneratedIpCountryResolver($dir)->countryFor(IpAddress::parse($ip) ?? throw new RuntimeException($ip));
}

it('resolves an address to its allocated country', function (): void {
    $dir = atlasIpSandbox(
        v4: [['41.60.0.0', '41.60.5.255', 'KE'], ['1.0.16.0', '1.0.31.255', 'JP']],
        v6: [['2c0f:f000::', '2c0f:f000:fff:ffff:ffff:ffff:ffff:ffff', 'KE']],
    );

    expect(atlasResolve('41.60.3.7', $dir))->toBe('KE')
        ->and(atlasResolve('1.0.20.1', $dir))->toBe('JP')
        ->and(atlasResolve('2c0f:f000:1::1', $dir))->toBe('KE');
});

it('returns null for an address in no allocated range', function (): void {
    $dir = atlasIpSandbox(v4: [['41.60.0.0', '41.60.5.255', 'KE']]);

    expect(atlasResolve('41.60.6.0', $dir))->toBeNull()
        ->and(atlasResolve('8.8.8.8', $dir))->toBeNull();
});

it('never asks the table about a reserved address', function (): void {
    // 10.0.0.1 is in use on millions of networks in every country there is.
    // Answering with whichever one a stale row happens to name would be worse
    // than answering nothing.
    $dir = atlasIpSandbox(v4: [['0.0.0.0', '255.255.255.255', 'KE']]);

    expect(atlasResolve('10.0.0.1', $dir))->toBeNull()
        ->and(atlasResolve('127.0.0.1', $dir))->toBeNull()
        ->and(atlasResolve('172.17.0.1', $dir))->toBeNull()
        ->and(atlasResolve('8.8.8.8', $dir))->toBe('KE');
});

it('separates a missing dataset from an unallocated address', function (): void {
    // Both are null from countryFor(). One is a deployment problem and the
    // other is just how the internet is, so isReady() answers which.
    $empty = new GeneratedIpCountryResolver(atlasIpSandbox());
    $loaded = new GeneratedIpCountryResolver(atlasIpSandbox(v4: [['1.0.0.0', '1.0.0.255', 'AU']]));

    expect($empty->isReady())->toBeFalse()
        ->and($loaded->isReady())->toBeTrue()
        ->and($empty->countryFor(IpAddress::parse('1.0.0.1')))->toBeNull()
        ->and($loaded->countryFor(IpAddress::parse('9.9.9.9')))->toBeNull();
});

it('loads each family independently', function (): void {
    // An application that only ever sees IPv4 should not pay for the v6 table.
    $dir = atlasIpSandbox(v4: [['1.0.0.0', '1.0.0.255', 'AU']]);
    $resolver = new GeneratedIpCountryResolver($dir);

    expect($resolver->counts())->toBe(['ipv4' => 1, 'ipv6' => 0])
        ->and(atlasResolve('2001:db8::1', $dir))->toBeNull();
});

it('skips a malformed row rather than losing the dataset', function (): void {
    // Registry files are third-party input and occasionally contain a line
    // nobody anticipated.
    $dir = atlasIpSandbox(v4: [
        ['1.0.0.0', '1.0.0.255', 'AU'],
        ['not-an-ip', '1.0.1.255', 'NZ'],
        ['2.0.0.0', 'also-not-an-ip', 'NZ'],
        ['3.0.0.0', '3.0.0.255', 'TOOLONG'],
        ['4.0.0.0', '4.0.0.255', 'US'],
    ]);

    expect(new GeneratedIpCountryResolver($dir)->counts()['ipv4'])->toBe(2)
        ->and(atlasResolve('1.0.0.1', $dir))->toBe('AU')
        ->and(atlasResolve('4.0.0.1', $dir))->toBe('US');
});
