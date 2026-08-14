# laranail/atlas

[![Packagist](https://img.shields.io/packagist/v/laranail/atlas.svg?style=flat-square)](https://packagist.org/packages/laranail/atlas)
[![Tests](https://img.shields.io/github/actions/workflow/status/laranail/atlas/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/laranail/atlas/actions/workflows/tests.yml)
[![Static analysis](https://img.shields.io/github/actions/workflow/status/laranail/atlas/static-analysis.yml?branch=main&label=static%20analysis&style=flat-square)](https://github.com/laranail/atlas/actions/workflows/static-analysis.yml)
[![License MIT](https://img.shields.io/badge/license-MIT-blue.svg?style=flat-square)](LICENSE)

> Countries, currencies, languages and coordinates for Laravel — a generated ISO catalogue with a
> swappable data source, distance and bounding-box maths, and offline IP-to-country lookup.

Requires PHP `^8.4.1 || ^8.5` and Laravel `^13.0`. Companion to
[`laranail/chrono`](https://opensource.simtabi.com/documentation/laranail/chrono/), which answers
*when*; this one answers *where*.

## Install

```bash
composer require laranail/atlas
```

No data package required — 250 countries ship with the package as a flat PHP array that OPcache
holds as compiled opcodes.

## <a name="documentation"></a>Documentation

Full documentation is at
**[opensource.simtabi.com/documentation/laranail/atlas](https://opensource.simtabi.com/documentation/laranail/atlas/)**.

## License

MIT. See [LICENSE](LICENSE).
