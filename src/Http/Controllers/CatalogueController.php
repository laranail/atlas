<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Atlas\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Simtabi\Laranail\Atlas\Services\AtlasService;

/**
 * The flat lists derived from the catalogue: currencies, languages, continents,
 * regions and subregions.
 *
 * One controller rather than five, because each is the same shape — a sorted
 * list of codes with no parameters — and five files that differ by one method
 * call would be five places to keep in step.
 */
final readonly class CatalogueController
{
    public function __construct(
        private AtlasService $atlas,
    ) {}

    public function currencies(): JsonResponse
    {
        return new JsonResponse(['data' => $this->atlas->currencies()]);
    }

    public function languages(): JsonResponse
    {
        return new JsonResponse(['data' => $this->atlas->languages()]);
    }

    public function continents(): JsonResponse
    {
        // The one endpoint whose payload is a map rather than a list, because
        // a continent code is unreadable without its name. It comes from the
        // form surface for that reason — the response shape is unchanged.
        return new JsonResponse(['data' => $this->atlas->form()->continents()]);
    }

    public function regions(): JsonResponse
    {
        return new JsonResponse(['data' => $this->atlas->regions()]);
    }

    public function subregions(): JsonResponse
    {
        return new JsonResponse(['data' => $this->atlas->subregions()]);
    }

    /**
     * Which data source answered, and how current it is.
     *
     * Deliberately on the API and not only in `doctor`: a consumer caching
     * responses needs to know when the catalogue underneath them changed, and
     * asking the server beats guessing from a package version.
     */
    public function describe(): JsonResponse
    {
        return new JsonResponse(['data' => $this->atlas->describe()]);
    }
}
