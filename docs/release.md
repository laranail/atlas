# Release

How a version of this package is cut, and what has to be current before it is.

## Versioning

Semantic versioning with **immutable tags**. Every release gets its own
`v0.1.x`, its own changelog section and its own GitHub release; a tag, once
pushed, is never re-pointed.

That is a deliberate departure from the moving-`v0.1.0` convention some laranail
packages still use. A moving tag means two machines resolving `^0.1` on the same
day can get different code, and a `composer.lock` recording `v0.1.0` tells you
nothing about what was installed. Consumers still constrain on `^0.1`; what they
get is a tag that says which build it is.

New SemVer minors begin at 1.0.

## The public surface

**Supported:** the `Atlas` facade, `Services\AtlasService`, everything under
`Core\Contracts`, the value types in `Core\Country`, `Core\Geo` and
`Core\Network`, the four `Enums`, the four `Rules`, the published
`config/laranail/atlas.php` keys, and the REST API's response shapes.

**Internal, free to change:** `Core\Support`, the adapters' constructors, and
everything under `tools/`.

## Before tagging

```bash
composer lint         # parallel-lint, Pint, PHPStan, deptrac guard, Rector
composer test         # Pest
composer sync-check   # generated enums match the generated dataset
composer validate --strict
composer audit
```

`sync-check` is the one specific to this package. The three generated enums are
built from `resources/data/countries.php`, and a release cut with a regenerated
dataset but un-regenerated enums ships a `Country` case the catalogue cannot
answer for — or a country in the data with no case. Neither fails a test that
does not look for it.

### Is the data current?

```bash
php tools/build-dataset.php
git diff --stat resources/data/
```

If the dataset moved, regenerate the enums and check the diff. Countries change
rarely but they do change — codes get reassigned, names change, currencies are
replaced.

The **IP table is not part of a release**. It is built by consumers who want it,
because registry delegation data changes daily and a snapshot in a tag would be
stale before anybody installed it.

## Optional-dependency jobs

Two CI jobs prove the optional pieces are optional:

- **without `laranail/chrono`** — the bridge must report unavailable and the
  suite must pass. An optional dependency that breaks the package when absent is
  a required dependency nobody declared.
- **without `ext-intl`** — `Core\Support\Text::fold()` falls back to its table.

Neither is a nice-to-have: both are properties the README claims, so they are
tested rather than asserted.

## Cutting it

1. Update `CHANGELOG.md` under the version being cut. The release workflow
   extracts that section verbatim as the GitHub release body — every release
   carries a human-readable summary, never auto-generated notes alone.
2. Commit.
3. Tag `vX.Y.Z` and push the tag. The workflow does the rest.

```bash
git tag -a v0.1.1 -m "…"
git push origin v0.1.1
```

## Distribution

Not Packagist. laranail packages resolve inter-package dependencies through git
**VCS repositories**, because force-pushed history has left stale cached clones
on Packagist for this family that only clear on a manual delete-and-resubmit.

A consumer adds:

```json
"repositories": [
  { "type": "vcs", "url": "https://github.com/laranail/atlas" }
]
```

and declares the **full transitive** `laranail/*` closure — Composer ignores a
dependency's own `repositories`, so the root package must list a `vcs` entry for
every laranail package it pulls, not only the direct ones.

---
[← Docs index](../README.md#documentation)
