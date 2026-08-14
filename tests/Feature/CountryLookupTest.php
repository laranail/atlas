<?php

declare(strict_types=1);

use Simtabi\Laranail\Atlas\Core\Country\PhoneRules;
use Simtabi\Laranail\Atlas\Facades\Atlas;

describe('lookups', function (): void {
    it('finds a country by its exact name', function (): void {
        expect(Atlas::query()->findByName('Kenya')?->iso2)->toBe('KE')
            ->and(Atlas::query()->findByName('kenya')?->iso2)->toBe('KE');
    });

    it('finds a country by its official or native name too', function (): void {
        $japan = Atlas::query()->find('JP');

        expect(Atlas::query()->findByName($japan->officialName)?->iso2)->toBe('JP')
            ->and(Atlas::query()->findByName($japan->nativeName)?->iso2)->toBe('JP');
    });

    it('returns nothing for a name no country has', function (): void {
        expect(Atlas::query()->findByName('Atlantis'))->toBeNull();
    });

    it('finds a country by calling code, with or without the plus', function (): void {
        expect(Atlas::query()->findByDialCode('254')?->iso2)->toBe('KE')
            ->and(Atlas::query()->findByDialCode('+254')?->iso2)->toBe('KE');
    });

    it('returns every country sharing a calling code', function (): void {
        // +1 is the whole North American Numbering Plan, not just the US.
        $nanp = Atlas::query()->allByDialCode('1');

        expect(count($nanp))->toBeGreaterThan(1)
            ->and(array_column($nanp, 'iso2'))->toContain('US');
    });

    it('returns nothing for a calling code nobody uses', function (): void {
        expect(Atlas::query()->findByDialCode('999'))->toBeNull()
            ->and(Atlas::query()->allByDialCode('999'))->toBe([]);
    });
});

describe('phone rules', function (): void {
    it('states an exact length where the numbering plan is well known', function (): void {
        $kenya = Atlas::query()->find('KE')->phone();

        expect($kenya->minLength)->toBe(9)
            ->and($kenya->maxLength)->toBe(9)
            ->and($kenya->exact)->toBeTrue();
    });

    it('falls back to E.164 bounds rather than inventing a length', function (): void {
        // The honest answer for a plan we have no source for. `exact` is what
        // tells a caller which of the two they are looking at.
        $rules = PhoneRules::forCallingCode('688');

        expect($rules->exact)->toBeFalse()
            ->and($rules->minLength)->toBe(PhoneRules::NATIONAL_MIN)
            ->and($rules->maxLength)->toBe(PhoneRules::E164_MAX - 3);
    });

    it('accepts a national number of a plausible length', function (): void {
        $kenya = Atlas::query()->find('KE')->phone();

        expect($kenya->accepts('712345678'))->toBeTrue()
            ->and($kenya->accepts('71234'))->toBeFalse();
    });

    it('matches a full number, however the user spaced it', function (): void {
        // Rejecting a valid number for its spaces teaches people to distrust
        // the form rather than to fix the number.
        $kenya = Atlas::query()->find('KE');

        expect($kenya->acceptsPhoneNumber('+254712345678'))->toBeTrue()
            ->and($kenya->acceptsPhoneNumber('+254 712 345 678'))->toBeTrue()
            ->and($kenya->acceptsPhoneNumber('254-712-345-678'))->toBeTrue()
            ->and($kenya->acceptsPhoneNumber('+254 712 345'))->toBeFalse();
    });

    it('reaches the rules by country code and by dial code', function (): void {
        expect(Atlas::query()->phoneRulesFor('KE')?->callingCode)->toBe('254')
            ->and(Atlas::query()->phoneRulesForDialCode('+254')->minLength)->toBe(9)
            ->and(Atlas::query()->phoneRulesFor('ZZ'))->toBeNull();
    });

    it('serialises for an API response', function (): void {
        $json = json_decode(json_encode(Atlas::query()->find('KE')->phone()), true);

        expect($json)->toHaveKeys(['callingCode', 'minLength', 'maxLength', 'exact', 'pattern']);
    });

    it('gives every country with a calling code some rules', function (): void {
        foreach (Atlas::countries() as $country) {
            $rules = $country->phone();

            if ($country->callingCode() === null) {
                expect($rules)->toBeNull();

                continue;
            }

            expect($rules->minLength)->toBeGreaterThanOrEqual(PhoneRules::NATIONAL_MIN)
                ->and($rules->maxLength)->toBeGreaterThanOrEqual($rules->minLength);
        }
    });
});
