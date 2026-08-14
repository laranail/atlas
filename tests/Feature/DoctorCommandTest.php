<?php

declare(strict_types=1);
use Illuminate\Contracts\Console\Kernel;

it('registers under a vendor-scoped name and nothing generic', function (): void {
    // Artisan's registry is a flat map. `atlas:doctor` is a name any package or
    // application could also want, and the loser is replaced without a word —
    // so this package claims only the namespaced one.
    $names = array_keys(app(Kernel::class)->all());

    expect($names)->toContain('laranail::atlas.doctor')
        ->and($names)->not->toContain('atlas:doctor')
        ->and($names)->not->toContain('doctor');
});

it('passes on a healthy installation', function (): void {
    $this->artisan('laranail::atlas.doctor')
        ->assertSuccessful();
});

it('warns rather than fails when the ip table is absent', function (): void {
    // An application using atlas for its country catalogue and nothing else is
    // entitled to skip a table built from five registry downloads.
    $this->artisan('laranail::atlas.doctor')
        ->expectsOutputToContain('countryForIp()')
        ->assertSuccessful();
});

it('treats that warning as a failure under --strict', function (): void {
    $this->artisan('laranail::atlas.doctor', ['--strict' => true])
        ->assertFailed();
});
