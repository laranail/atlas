# Installation

Requirements, the install, and what to publish — plus the one dataset that is
built rather than shipped.

## Requirements

| | |
|---|---|
| PHP | `^8.4.1 \|\| ^8.5` |
| Laravel | `^13.0` |
| Extensions | none required |

`ext-intl` is used when present, for locale-aware transliteration in
`Core\Support\Text::fold()`. Without it a built-in table answers instead, so
the package works either way — the results differ only for scripts the table
does not cover.

## Install

```bash
composer require laranail/atlas
```

Nothing else to run. **250 countries ship with the package** as a flat PHP
array that OPcache holds as compiled opcodes, so a lookup costs an array read
and no data package, no database table and no migration is involved.

The service provider and the `Atlas` facade are discovered automatically.

## What you can publish

Nothing has to be published for the package to work.

```bash
# The config file → config/laranail/atlas.php
php artisan vendor:publish --tag="laranail::atlas-config"

# Validation messages → lang/vendor/laranail-atlas/
php artisan vendor:publish --tag="laranail::atlas-translations"
```

Publish tags are namespaced (`laranail::atlas-*`) because `vendor:publish`
keeps its tags in a flat map — a bare `atlas` tag would be a plausible
collision, and the loser is replaced silently.

## The IP table is built, not shipped

`Atlas::countryForIp()` needs a range table derived from the five regional
registries' delegation files. That table is **not in the package**, because it
changes daily and would bloat every install of a package most people want for
its country catalogue.

```bash
php vendor/laranail/atlas/tools/build-ip-table.php
```

Until you run it, `countryForIp()` returns `null` for every address — which is
indistinguishable from "this address is not allocated" unless something says
so. `laranail::atlas.doctor` says so, and the API's `/ip/{ip}` endpoint returns
`reason: table_not_installed`.

If you need city, ISP or VPN detection, none of that is in registry data at
all: see [`laranail/ip-intel`](https://opensource.simtabi.com/documentation/laranail/ip-intel/),
which uses this as its offline tier.

## Optional companions

| Package | What it adds | How it degrades |
|---|---|---|
| [`laranail/chrono`](https://opensource.simtabi.com/documentation/laranail/chrono/) | `timezonesFor()` and `primaryTimezoneFor()` on a country | The bridge reports unavailable; nothing throws |
| `rinvex/countries` | An alternative data source | Only if you set `ATLAS_PROVIDER=rinvex` |

Both are `suggest`, not `require`. The chrono bridge is `class_exists`-guarded,
and the default CI suite runs **without** chrono — its `^8.5` floor means it
cannot be a dev dependency without breaking the 8.4 leg — so the absent-path is
proved on every push. A separate job installs it to prove the present-path is
not skipping silently.

## Verify

```bash
php artisan laranail::atlas.doctor
```

Reports which source answered, how many countries it holds, the dataset's age,
and whether the IP table is installed.

---
[← Docs index](../README.md#documentation)
