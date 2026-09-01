<?php

declare(strict_types=1);

use Simtabi\Laranail\Atlas\Providers\AtlasServiceProvider;

it('registers no routes while the api is disabled', function (): void {
    // Off means absent, not registered-then-blocked. An endpoint that appears
    // in route:list while "disabled" is one loosened middleware group away from
    // being live, and nobody reviewing that change would look here.
    expect(config('laranail.atlas.api.enabled'))->toBeFalse();

    $routes = collect(app('router')->getRoutes()->getRoutes())
        ->filter(fn ($route): bool => str_contains((string) $route->uri(), 'atlas'));

    expect($routes)->toBeEmpty();
});

describe('with the api enabled', function (): void {
    beforeEach(function (): void {
        config()->set('laranail.atlas.api.enabled', true);
        config()->set('laranail.atlas.api.middleware', ['api']);

        // Re-register so the routes are added; the provider only loads them
        // when the flag is on at boot.
        app()->register(AtlasServiceProvider::class, force: true);
    });

    it('lists countries', function (): void {
        $this->getJson('api/atlas/v1/countries?limit=5')
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonStructure(['data' => [['iso2', 'iso3', 'name', 'flag', 'currencies']]]);
    });

    it('filters by currency', function (): void {
        $response = $this->getJson('api/atlas/v1/countries?currency=EUR')->assertOk();

        expect($response->json('data'))->not->toBeEmpty();

        foreach ($response->json('data') as $country) {
            expect($country['currencies'])->toContain('EUR');
        }
    });

    it('rejects a currency no country uses', function (): void {
        // A 422 naming the field, not an empty 200 — which would read as "no
        // country uses the euro" rather than "that is not a currency".
        $this->getJson('api/atlas/v1/countries?currency=XYZ')
            ->assertStatus(422)
            ->assertJsonValidationErrorFor('currency');
    });

    it('caps the limit rather than serialising everything', function (): void {
        $this->getJson('api/atlas/v1/countries?limit=5000')
            ->assertStatus(422)
            ->assertJsonValidationErrorFor('limit');
    });

    it('answers for one country by any of its three codes', function (string $code): void {
        $this->getJson("api/atlas/v1/countries/{$code}")
            ->assertOk()
            ->assertJsonPath('data.iso2', 'KE')
            ->assertJsonPath('data.name', 'Kenya')
            ->assertJsonPath('data.flag', '🇰🇪');
    })->with(['KE', 'ke', 'KEN', '404']);

    it('404s a code that names no country', function (): void {
        // Not an empty 200: a typo should not look like a country with no
        // fields.
        $this->getJson('api/atlas/v1/countries/ZZ')->assertNotFound();
    });

    it('serves the flat catalogues', function (string $path): void {
        $response = $this->getJson("api/atlas/v1/{$path}")->assertOk();

        expect($response->json('data'))->not->toBeEmpty();
    })->with(['currencies', 'languages', 'continents', 'regions', 'subregions']);

    it('measures a distance between two coordinates', function (): void {
        // London to Paris, ~344 km.
        $response = $this->getJson('api/atlas/v1/distance?from=51.5074,-0.1278&to=48.8566,2.3522')
            ->assertOk();

        expect($response->json('data.kilometres'))->toBeGreaterThan(330.0)
            ->and($response->json('data.kilometres'))->toBeLessThan(360.0);
    });

    it('measures a distance between two countries', function (): void {
        $this->getJson('api/atlas/v1/distance?from=KE&to=TZ')
            ->assertOk()
            ->assertJsonStructure(['data' => ['metres', 'kilometres', 'miles', 'unit', 'value', 'formatted']]);
    });

    it('names which side of a distance was wrong', function (string $query, string $field): void {
        $this->getJson('api/atlas/v1/distance?'.$query)
            ->assertStatus(422)
            ->assertJsonValidationErrorFor($field);
    })->with([
        ['from=91.0,0.0&to=KE', 'from'],
        ['from=KE&to=nonsense,here', 'to'],
        ['from=ZZ&to=KE', 'from'],
        ['from=KE&to=ZZ', 'to'],
    ]);

    it('requires both ends of a distance', function (): void {
        $this->getJson('api/atlas/v1/distance?from=KE')
            ->assertStatus(422)
            ->assertJsonValidationErrorFor('to');
    });

    it('reports an unresolvable address as a 422', function (): void {
        $this->getJson('api/atlas/v1/ip/not-an-address')->assertStatus(422);
    });

    it('answers 200 with a reason when an address places nowhere', function (): void {
        // The question was well formed and the answer is "nowhere". A 404 would
        // say the endpoint does not exist.
        $this->getJson('api/atlas/v1/ip/10.0.0.1')
            ->assertOk()
            ->assertJsonPath('data', null)
            ->assertJsonPath('reason', 'reserved');
    });

    it('describes its own data source', function (): void {
        $this->getJson('api/atlas/v1/describe')
            ->assertOk()
            ->assertJsonStructure(['data' => ['provider', 'version', 'available', 'countries', 'distance', 'ip_ready']]);
    });

    it('is read-only', function (string $path): void {
        $this->postJson("api/atlas/v1/{$path}")->assertStatus(405);
    })->with(['countries', 'currencies', 'distance']);

    it('honours a configured prefix', function (): void {
        config()->set('laranail.atlas.api.prefix', 'internal/places');
        app()->register(AtlasServiceProvider::class, force: true);

        $this->getJson('internal/places/v1/countries?limit=1')->assertOk();
    });
});
