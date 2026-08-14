<?php

declare(strict_types=1);

use Simtabi\Laranail\Atlas\Core\Support\Text;

/**
 * The fold exists because `iconv('UTF-8', 'ASCII//TRANSLIT', …)` is not portable:
 *
 *   "Côte d'Ivoire"  →  glibc (Linux):  "Cote d'Ivoire"
 *                    →  BSD   (macOS):  "C^ote d'Ivoire"
 *
 * The BSD form breaks `str_contains(fold(…), 'cote')`, so the country cannot be
 * found by typing its name without accents. That was the first implementation
 * here and it would have passed on a glibc CI runner while failing on every Mac.
 *
 * These assertions must hold on both, which is the whole point.
 */
it('strips diacritics from a latin name', function (string $accented, string $expected): void {
    expect(Text::fold($accented))->toBe($expected);
})->with([
    "Côte d'Ivoire" => ["Côte d'Ivoire", "cote d'ivoire"],
    'Türkiye' => ['Türkiye', 'turkiye'],
    'Åland Islands' => ['Åland Islands', 'aland islands'],
    'São Tomé' => ['São Tomé', 'sao tome'],
    'Curaçao' => ['Curaçao', 'curacao'],
    'Réunion' => ['Réunion', 'reunion'],
    'Saint Barthélemy' => ['Saint Barthélemy', 'saint barthelemy'],
]);

it('never leaves a combining mark as a visible character', function (): void {
    // The specific BSD failure: the accent becomes a literal ^, ~ or ` that no
    // one would ever type.
    foreach (['Côte', 'São', 'Türkiye', 'Åland', 'Curaçao'] as $name) {
        expect(Text::fold($name))->not->toContain('^')
            ->and(Text::fold($name))->not->toContain('~')
            ->and(Text::fold($name))->not->toContain('`')
            ->and(Text::fold($name))->not->toContain('"');
    }
});

it('lower-cases', function (): void {
    expect(Text::fold('KENYA'))->toBe('kenya');
});

it('trims', function (): void {
    expect(Text::fold('  Kenya  '))->toBe('kenya');
});

it('is a no-op for an empty string', function (string $value): void {
    expect(Text::fold($value))->toBe('');
})->with(['', '   ', "\t\n"]);

it('leaves a non-latin script alone rather than mangling it', function (): void {
    // There is no ASCII form of these a user would type, so approximating one
    // helps nobody. Whatever comes back must at least be non-empty and stable.
    $first = Text::fold('日本');
    $second = Text::fold('日本');

    expect($first)->toBe($second)->not->toBe('');
});

it('is idempotent', function (): void {
    $once = Text::fold("Côte d'Ivoire");

    expect(Text::fold($once))->toBe($once);
});

it('gives the same answer with and without ext-intl', function (): void {
    // ext-intl is not required by this package, so the table below the ICU path
    // is a live code path on some installs — not a theoretical fallback. Running
    // the suite on a host with intl loaded would otherwise never touch it, and
    // the two could drift silently.
    $table = new ReflectionClass(Text::class)->getConstant('TRANSLITERATIONS');

    expect($table)->toBeArray();

    foreach (["Côte d'Ivoire", 'Türkiye', 'Åland Islands', 'São Tomé', 'Curaçao', 'Réunion'] as $name) {
        $withoutIntl = mb_strtolower(strtr($name, $table), 'UTF-8');

        expect(Text::fold($name))->toBe($withoutIntl, "the two fold paths disagree on [{$name}]");
    }
});
