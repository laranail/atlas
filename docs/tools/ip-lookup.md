# IP to country

Offline, over registry delegation data. No network call and no API key —
and **country only**.

## The table is built, not shipped

```bash
php vendor/laranail/atlas/tools/build-ip-table.php
```

Until you run it, `countryForIp()` returns `null` for every address. The table
is not in the package because registry data changes daily and would be stale the
day it was tagged, and because most installs want the country catalogue and not
this.

## Looking one up

```php
use Simtabi\Laranail\Atlas\Facades\Atlas;

Atlas::countryForIp('41.90.0.1');    // ?CountryRecord
Atlas::countryForIp($ipAddress);     // or a parsed Core\Network\IpAddress
```

## Null means three different things

That is the trap, and it is why `describe()` and `doctor` exist:

| Cause | How to tell |
|---|---|
| A **reserved** address (RFC 1918, loopback, link-local) | `$ip->isReserved()` |
| The **table is not installed** | `Atlas::describe()['ip_ready'] === false` |
| A genuine **registry gap** | Neither of the above |

The middle one is a deployment problem and the others are just how the internet
is. A bare null makes them identical, so the [API](api.md#get-ipip) returns an
explicit `reason` and `laranail::atlas.doctor` says which.

## `Core\Network\IpAddress`

```php
use Simtabi\Laranail\Atlas\Core\Network\IpAddress;

$ip = IpAddress::parse('192.168.1.1');   // ?IpAddress — null when it is not one
```

| Member | Answers |
|---|---|
| `isV4()` / `isV6()` | Which family |
| `isPublic()` | Routable on the public internet |
| `isReserved()` | Any special-purpose range |
| `isLoopback()` | `127.0.0.0/8`, `::1` |
| `isPrivate()` | RFC 1918 and its v6 equivalent |
| `mappedV4()` | The v4 address inside a `::ffff:` v6 address |

### Parsing goes through `filter_var`, always

Hand-rolled octet parsing accepts `01.02.03.04`, which most of the internet
treats as octal and reads as `1.2.3.4`. It also tends to accept things with
trailing whitespace, leading `+`, and other shapes that differ between whatever
parsed it and whatever acts on it. `filter_var` is the one parser here.

### `172.16.0.0/12` is twelve, not sixteen

RFC 1918's middle block runs `172.16.0.0`–`172.**31**.255.255`. Writing it as
ending at `172.16.255.255` classifies **fifteen of its sixteen sub-blocks as
public** — including `172.17.0.0/16`, which is Docker's default bridge network.

That matters wherever `isPublic()` gates an outbound request: a containerised
application's own internal range passing as public is an SSRF filter with a hole
in it. This package gets the boundary right, and a test pins `172.31.0.1` as
private specifically.

### IPv6 comparison

Addresses are compared as `inet_pton()` binary strings with `strcmp`. PHP has no
native uint128, and `inet_pton` output is big-endian, so lexicographic order
equals numeric order — no GMP, no bcmath, and NUL-safe because `strcmp` is
binary-safe.

## What is not here

City, ISP name, VPN/proxy status, ASN organisation. **None of that is in
registry delegation data** and none of it can be derived from it, so this
package answers country and stops rather than guessing.

[`laranail/ip-intel`](https://opensource.simtabi.com/documentation/laranail/ip-intel/)
is where those live. It uses this as its free offline tier, so a country-only
question never reaches a metered API.

---
[← Docs index](../../README.md#documentation)
