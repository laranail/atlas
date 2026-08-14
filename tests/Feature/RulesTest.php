<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use Simtabi\Laranail\Atlas\Rules\Coordinate;
use Simtabi\Laranail\Atlas\Rules\CountryCode;
use Simtabi\Laranail\Atlas\Rules\CurrencyCode;
use Simtabi\Laranail\Atlas\Rules\LanguageCode;

function passes(object $rule, mixed $value): bool
{
    return Validator::make(['field' => $value], ['field' => [$rule]])->passes();
}

it('accepts every ISO form of a country code', function (string $code): void {
    expect(passes(new CountryCode, $code))->toBeTrue();
})->with(['KE', 'ke', 'KEN', 'GB', 'GBR', 'US', 'USA']);

it('refuses a well-shaped code that names no country', function (mixed $code): void {
    // The reason this rule exists rather than `size:2`. Every one of these
    // passes a length check, and `UK` in particular is the code people reach
    // for and is not one — Britain is GB.
    expect(passes(new CountryCode, $code))->toBeFalse();
})->with(['XX', 'ZZ', 'UK', 'EN', 'KENYA', 123, [['KE']]]);

it('leaves an empty string to `required`, and rejects a null', function (): void {
    // These two are not the same, which is surprising enough to pin. Laravel's
    // presentOrRuleIsImplicit() short-circuits on a string that trims to
    // nothing and skips every non-implicit rule — so `''` reaches this rule
    // never. A null is *present* (the key exists), so the rule does run, and
    // rejects it.
    //
    // The consequence for a caller: a rules() array without `required` accepts
    // an empty string silently. That is the framework's contract, not this
    // rule's to override — an implicit rule here would make `sometimes` mean
    // nothing.
    expect(passes(new CountryCode, ''))->toBeTrue()
        ->and(passes(new CountryCode, null))->toBeFalse();

    expect(Validator::make(['field' => ''], ['field' => ['required', new CountryCode]])->passes())->toBeFalse();
});

it('says what would have been accepted', function (): void {
    // A message reading "the selected field is invalid" tells somebody staring
    // at UK nothing, and GB is the answer.
    $errors = Validator::make(['field' => 'UK'], ['field' => [new CountryCode]])->errors();

    expect($errors->first('field'))->toContain('alpha-2')
        ->and($errors->first('field'))->toContain('GB');
});

it('accepts a currency some country uses', function (string $code): void {
    expect(passes(new CurrencyCode, $code))->toBeTrue();
})->with(['USD', 'usd', 'EUR', 'KES', 'GBP', 'JPY']);

it('refuses a currency no country uses', function (mixed $code): void {
    expect(passes(new CurrencyCode, $code))->toBeFalse();
})->with(['XYZ', 'ABC', 'DOLLARS', 42]);

it('accepts a language spoken somewhere in the catalogue', function (string $code): void {
    // ISO 639-3, three letters — which is what the dataset carries, and what
    // the `Language` enum is generated from. Not 639-1: `en` is not in here,
    // `eng` is, and a rule that quietly accepted both would let a value be
    // stored that nothing downstream can match against a country.
    expect(passes(new LanguageCode, $code))->toBeTrue();
})->with(['eng', 'ENG', 'swa', 'fra', 'ara']);

it('refuses a language nothing in the catalogue speaks', function (mixed $code): void {
    expect(passes(new LanguageCode, $code))->toBeFalse();
})->with(['zzz', 'klingon', 'en', 'sw', 7]);

it('accepts a coordinate pair', function (string $pair): void {
    expect(passes(new Coordinate, $pair))->toBeTrue();
})->with([
    '51.5074,-0.1278',
    '0,0',
    '-90,180',
    '90,-180',
    ' 51.5074 , -0.1278 ',
    // Longitude past ±180 is deliberately accepted: 181° east is a real place,
    // and Coordinates wraps it. Rejecting it would break arithmetic that
    // legitimately crosses the antimeridian.
    '0,181',
    '0,-200',
]);

it('refuses a pair that is not one', function (mixed $value): void {
    expect(passes(new Coordinate, $value))->toBeFalse();
})->with([
    '51.5074',
    '51.5074,-0.1278,3',
    'here,there',
    ',',
    // NAN and INF pass a `numeric` check and then propagate silently through
    // every distance calculation downstream.
    'NAN,0',
    '0,INF',
    51.5,
    null,
]);

it('refuses a latitude off the planet', function (string $pair): void {
    expect(passes(new Coordinate, $pair))->toBeFalse();
})->with(['91,0', '-90.1,0', '1000,0']);
