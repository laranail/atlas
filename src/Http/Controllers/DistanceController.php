<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Atlas\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Simtabi\Laranail\Atlas\Core\Geo\Distance;
use Simtabi\Laranail\Atlas\Core\Geo\Coordinates;
use Simtabi\Laranail\Atlas\Core\Geo\DistanceUnit;
use Simtabi\Laranail\Atlas\Services\AtlasService;
use Simtabi\Laranail\Atlas\Http\Requests\DistanceRequest;

/**
 * `GET /distance?from=&to=&unit=` — between two points, two countries, or one
 * of each.
 */
final readonly class DistanceController
{
    public function __construct(
        private AtlasService $atlas,
    ) {}

    public function __invoke(DistanceRequest $request): JsonResponse
    {
        $from = $request->from();
        $to = $request->to();

        $distance = $from instanceof Coordinates && $to instanceof Coordinates
            ? $this->atlas->distance($from, $to)
            : $this->measureMixed($from, $to);

        if (! $distance instanceof Distance) {
            // Both arguments validated, so this is the case where a country in
            // the catalogue carries no centroid — a gap in the data rather than
            // a bad request, which is what 422 would say.
            return new JsonResponse([
                'message' => 'One of those places has no coordinates in the catalogue, so the distance cannot be measured.',
            ], 409);
        }

        $unit = DistanceUnit::from($request->unit());

        return new JsonResponse([
            'data' => $distance->toArray() + [
                'unit'      => $unit->value,
                'value'     => $distance->in($unit),
                'formatted' => $distance->format($unit),
            ],
        ]);
    }

    /**
     * At least one side is a country code.
     *
     * Countries are measured centroid to centroid, so this resolves each side
     * to a point and hands both to the same calculator — rather than having a
     * separate country-to-country path that could disagree with the coordinate
     * one about which formula is configured.
     */
    private function measureMixed(Coordinates|string $from, Coordinates|string $to): ?Distance
    {
        $a = $this->point($from);
        $b = $this->point($to);

        if (! $a instanceof Coordinates || ! $b instanceof Coordinates) {
            return null;
        }

        return $this->atlas->distance($a, $b);
    }

    private function point(Coordinates|string $value): ?Coordinates
    {
        return $value instanceof Coordinates
            ? $value
            : $this->atlas->country($value)?->coordinates;
    }
}
