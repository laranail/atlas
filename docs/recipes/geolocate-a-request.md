# Geolocate a request

Country only, offline, with no API key.

## Build the table first

```bash
php vendor/laranail/atlas/tools/build-ip-table.php
```

Without it `countryForIp()` answers `null` for every address. See
[installation](../installation.md#the-ip-table-is-built-not-shipped).

## Look it up

```php
use Simtabi\Laranail\Atlas\Facades\Atlas;

$country = Atlas::countryForIp($request->ip());   // ?CountryRecord
```

## Handle the null properly

`null` means three different things, and treating them alike is how a
deployment problem gets mistaken for the shape of the internet:

```php
use Simtabi\Laranail\Atlas\Core\Network\IpAddress;

$ip = IpAddress::parse($request->ip());

$reason = match (true) {
    ! $ip instanceof IpAddress            => 'unparseable',
    $ip->isReserved()                     => 'reserved',      // 10.x in dev
    Atlas::describe()['ip_ready'] === false => 'no_table',     // fix this one
    default                               => 'not_allocated',
};
```

**Never default to a country.** The implementation this replaces fell back to
the United States, which is a confidently wrong answer on every request from
localhost — and one that shows up as a tax rate, a currency or a consent banner
rather than as an error.

## If you are behind Cloudflare, you already have it

`CF-IPCountry` is set at the edge, free, before any code runs. Reading a header
beats any lookup:

```php
$code = $request->header('CF-IPCountry');
$country = $code === null ? null : Atlas::country($code);
```

Watch for the sentinels: Cloudflare sends `XX` for unknown and `T1` for Tor.
Both pass a naive two-letter check and neither is a country.

[`laranail/ip-intel`](https://opensource.simtabi.com/documentation/laranail/ip-intel/)
does exactly this chain — edge header, then this package's offline table, then
optionally a metered API — so a country-only question makes no network call.

## What you cannot get here

City, ISP, VPN or proxy status. None of it is in registry data. See
[ip-intel](https://opensource.simtabi.com/documentation/laranail/ip-intel/).

---
[← Docs index](../../README.md#documentation)
