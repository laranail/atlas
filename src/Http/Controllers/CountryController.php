<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Atlas\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Simtabi\Laranail\Atlas\Core\Country\CountryRecord;
use Simtabi\Laranail\Atlas\Http\Requests\CountryIndexRequest;
use Simtabi\Laranail\Atlas\Http\Resources\CountryResource;
use Simtabi\Laranail\Atlas\Services\AtlasService;

/**
 * Read-only country lookup.
 *
 * The whole controller is two methods over {@see AtlasService}, which is the
 * point: the API is a thin surface on the same object a consumer would inject,
 * so nothing can be true over HTTP that is not true in PHP.
 */
final readonly class CountryController
{
    public function __construct(
        private AtlasService $atlas,
    ) {}

    public function index(CountryIndexRequest $request): AnonymousResourceCollection
    {
        return CountryResource::collection(
            $request->applyTo($this->atlas->query())->get(),
        );
    }

    /**
     * One country, by alpha-2, alpha-3 or numeric code.
     *
     * 404 rather than an empty 200: a code that names no country is a wrong
     * question, and answering it with `{"data": null}` makes a typo look like
     * a country with no fields.
     */
    public function show(string $code): CountryResource|JsonResponse
    {
        $country = $this->atlas->country($code);

        if (! $country instanceof CountryRecord) {
            return new JsonResponse([
                'message' => 'No country matches that code.',
            ], 404);
        }

        return new CountryResource($country);
    }
}
