<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Lang;
use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Console\Kernel;

/**
 * Every name this package registers into a framework-owned registry.
 *
 * These registries are flat maps keyed by the name, so a second package
 * claiming the same key does not collide loudly — it **silently replaces** the
 * first, and the damage surfaces somewhere else entirely as a missing
 * translation, the wrong middleware, or a command that runs someone else's
 * code. A slug like `atlas` is a plausible collision with a sibling package, a
 * third-party one, or the consuming application's own.
 *
 * ## Why these assertions read the registry and not the provider
 *
 * Grepping `AtlasServiceProvider` proves how the registration was *written*,
 * not what the framework ended up *holding*. `Package::hasTranslations()` with
 * no argument derives its namespace from `->name()`, so the name under test is
 * never spelled out in the provider at all — a guard that grepped for
 * `laranail-atlas` there would pass while the framework held something else,
 * and would break the day package-tools changed its default. Asking the live
 * map survives both.
 */
it('registers its translations under vendor and slug, never a bare one', function (): void {
    $loader = Lang::getLoader();

    expect($loader->namespaces())->toHaveKey('laranail/atlas')
        ->and($loader->namespaces())->not->toHaveKey('atlas');
});

it('resolves a real string through that namespace', function (): void {
    // The namespace being registered is not the same claim as the files being
    // reachable through it. A wrong path registers fine and returns the key.
    $message = __('laranail/atlas::validation.country_code');

    expect($message)->toBeString()
        ->and($message)->not->toBe('laranail/atlas::validation.country_code')
        ->and($message)->toContain('ISO 3166-1');
});

it('publishes under vendor-scoped tags and claims no bare one', function (): void {
    // A list of group names, not a map keyed by them — worth pinning, because
    // reading it as a map yields integer keys and an assertion that passes
    // against nothing.
    $groups = ServiceProvider::publishableGroups();

    // Nothing generic. `atlas-config` is a name any package touching a country
    // catalogue could pick, and `vendor:publish --tag=atlas-config` would then
    // publish whichever registered last.
    expect($groups)->not->toContain('atlas')
        ->and($groups)->not->toContain('atlas-config')
        ->and($groups)->not->toContain('atlas-translations')
        ->and($groups)->not->toContain('config');

    $ours = array_values(array_filter(
        $groups,
        static fn (string $group): bool => str_contains($group, 'atlas'),
    ));

    expect($ours)->toEqualCanonicalizing([
        'laranail::atlas-config',
        'laranail::atlas-translations',
    ]);
});

it('registers its command under the laranail::<slug>.<command> shape', function (): void {
    // Artisan's registry is the same kind of flat map. `atlas:doctor` is a name
    // any package or application could also want, and the loser is replaced
    // without a word.
    $names = array_keys(app(Kernel::class)->all());

    expect($names)->toContain('laranail::atlas.doctor')
        ->and($names)->not->toContain('atlas:doctor')
        ->and($names)->not->toContain('doctor');
});

it('reads its config from the vendor-namespaced key', function (): void {
    expect(config('laranail.atlas.provider'))->not->toBeNull()
        ->and(config('atlas'))->toBeNull();
});

it('registers no view namespace, having no views to register', function (): void {
    // Asserted rather than assumed: `hasViews()` is one line away in the
    // provider, and the default it would register is worth pinning before
    // somebody adds it with a bare slug.
    expect(array_keys(app('view')->getFinder()->getHints()))->not->toContain('atlas');
});
