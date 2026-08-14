<?php

declare(strict_types=1);

use Simtabi\Laranail\Atlas\Core\Support\DatasetVersion;

// -----------------------------------------------------------------------
// The regression these exist for
// -----------------------------------------------------------------------

it('ages the stamp the generator actually writes', function (): void {
    // The whole bug in one assertion. doctor asked strtotime() to age
    // 'rinvex/countries v9.1.0', got false, and reported every dataset as
    // current — a health check that could not fail, which reads as a passing one.
    $version = DatasetVersion::parse('2025-07-14 rinvex/countries v9.1.0');

    expect($version->isDated())->toBeTrue()
        ->and($version->isOlderThan(new DateTimeImmutable('2026-08-14')))->toBeTrue()
        ->and($version->isOlderThan(new DateTimeImmutable('2025-01-01')))->toBeFalse();
});

it('refuses to age the old undated stamp instead of calling it current', function (): void {
    // Unknown is not current. Every dataset built before the stamp carried a
    // date lands here, and so does any custom source with its own format.
    $version = DatasetVersion::parse('rinvex/countries v9.1.0');

    expect($version->isDated())->toBeFalse()
        ->and($version->date)->toBeNull()
        ->and($version->source)->toBe('rinvex/countries v9.1.0')
        ->and($version->isOlderThan(new DateTimeImmutable('2099-01-01')))->toBeFalse();
});

// -----------------------------------------------------------------------
// Parsing
// -----------------------------------------------------------------------

it('splits the date off the provenance', function (): void {
    $version = DatasetVersion::parse('2025-07-14 rinvex/countries v9.1.0');

    expect($version->date)->toBe('2025-07-14')
        ->and($version->source)->toBe('rinvex/countries v9.1.0')
        ->and($version->raw)->toBe('2025-07-14 rinvex/countries v9.1.0');
});

it('keeps the raw stamp intact for display', function (): void {
    $raw = '2025-07-14 rinvex/countries v9.1.0';

    expect((string) DatasetVersion::parse($raw))->toBe($raw);
});

it('tolerates surrounding whitespace, since the stamp is read from a file', function (): void {
    // dataset-version.txt ends in a newline.
    $version = DatasetVersion::parse("  2025-07-14 rinvex/countries v9.1.0\n");

    expect($version->date)->toBe('2025-07-14')
        ->and($version->source)->toBe('rinvex/countries v9.1.0');
});

it('takes a bare date as both the date and the source', function (): void {
    $version = DatasetVersion::parse('2025-07-14');

    expect($version->date)->toBe('2025-07-14')
        ->and($version->source)->toBe('2025-07-14');
});

it('rejects a date-shaped string that is not a day', function (string $candidate): void {
    // The failure a regex misses: PHP rolls 2025-13-45 forward into 2026 rather
    // than rejecting it, so the round-trip comparison is what catches it.
    $version = DatasetVersion::parse($candidate . ' rinvex/countries v9.1.0');

    expect($version->isDated())->toBeFalse();
})->with(['2025-13-01', '2025-02-30', '2025-00-10', '25-07-14', '2025-7-14']);

it('refuses a loose date the old check would have swallowed', function (string $raw): void {
    // strtotime() accepts all of these. Narrow parsing is the fix, not a
    // limitation: guessing at free text is what went wrong.
    expect(DatasetVersion::parse($raw)->isDated())->toBeFalse();
})->with(['yesterday', 'v9.1.0', '14 July 2025', 'next monday', '']);

// -----------------------------------------------------------------------
// Comparison
// -----------------------------------------------------------------------

it('treats the cutoff day itself as not yet stale', function (): void {
    $version = DatasetVersion::parse('2025-07-14 rinvex/countries v9.1.0');

    expect($version->isOlderThan(new DateTimeImmutable('2025-07-14')))->toBeFalse()
        ->and($version->isOlderThan(new DateTimeImmutable('2025-07-15')))->toBeTrue();
});

it('reads the date at midnight rather than at the current time of day', function (): void {
    // Without the ! reset, createFromFormat fills the time from now, so the
    // same stamp compares differently depending on when the check runs.
    $date = DatasetVersion::parse('2025-07-14 rinvex/countries v9.1.0')->toDate();

    expect($date?->format('Y-m-d H:i:s'))->toBe('2025-07-14 00:00:00');
});

it('has no date to hand back when it could not parse one', function (): void {
    expect(DatasetVersion::parse('rinvex/countries v9.1.0')->toDate())->toBeNull();
});
