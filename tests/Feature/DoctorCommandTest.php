<?php

declare(strict_types=1);
use Simtabi\Laranail\Atlas\Core\Contracts\PlaceRepository;
use Simtabi\Laranail\Atlas\Services\AtlasService;

/**
 * Swap the data source for one that reports a chosen version stamp.
 *
 * Age is the one thing `doctor` checks that cannot be exercised against the
 * shipped dataset without a clock: a real stamp answers differently depending on
 * the day the suite runs. A stub fixes the input so the assertion is about the
 * check rather than about the calendar.
 */
function atlasDoctorStub(?string $version): void
{
    app()->instance(PlaceRepository::class, new readonly class($version) implements PlaceRepository
    {
        public function __construct(private ?string $version) {}

        public function all(): array
        {
            return [];
        }

        public function find(string $code): null
        {
            return null;
        }

        public function isAvailable(): bool
        {
            return true;
        }

        public function version(): ?string
        {
            return $this->version;
        }
    });

    app()->forgetInstance(AtlasService::class);
}

// The command's *name* is asserted in NamingConventionTest, beside every other
// registry this package writes into — they fail for one reason and are read
// together.

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

// -----------------------------------------------------------------------
// Age — the question the command silently stopped answering
// -----------------------------------------------------------------------

it('warns that a dataset older than a year is stale', function (): void {
    // This is the regression. The check ran strtotime() over the whole stamp,
    // which returns false for anything this package has ever written, so it
    // reported every dataset as current — and a health check that cannot fail
    // reads as one that passed.
    atlasDoctorStub('2000-01-01 rinvex/countries v1.0.0');

    $this->artisan('laranail::atlas.doctor')
        ->expectsOutputToContain('over a year ago')
        ->assertSuccessful();
});

it('fails on a stale dataset under --strict', function (): void {
    atlasDoctorStub('2000-01-01 rinvex/countries v1.0.0');

    $this->artisan('laranail::atlas.doctor', ['--strict' => true])
        ->assertFailed();
});

it('reports a stamp with no date as undatable rather than as current', function (): void {
    // Every dataset built before the stamp carried a date, plus any custom
    // source with its own format. Unknown is not current.
    atlasDoctorStub('rinvex/countries v9.1.0');

    $this->artisan('laranail::atlas.doctor')
        ->expectsOutputToContain('carries no date')
        ->assertSuccessful();
});

it('says so when the source reports no version at all', function (): void {
    atlasDoctorStub(null);

    $this->artisan('laranail::atlas.doctor')
        ->expectsOutputToContain('does not report a version')
        ->assertSuccessful();
});

it('shows the release date and no age warning for a current dataset', function (): void {
    atlasDoctorStub(date('Y-m-d') . ' rinvex/countries v9.9.9');

    $this->artisan('laranail::atlas.doctor')
        ->expectsOutputToContain('Source released')
        ->doesntExpectOutputToContain('over a year ago')
        ->assertSuccessful();
});
