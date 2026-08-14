<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Atlas\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Simtabi\Laranail\Atlas\Core\Country\CountryRecord;
use Simtabi\Laranail\Atlas\Core\Network\IpAddress;
use Simtabi\Laranail\Atlas\Http\Resources\CountryResource;
use Simtabi\Laranail\Atlas\Services\AtlasService;

/**
 * `GET /ip/{ip}` — the country an address was allocated to.
 *
 * Country and nothing else. City, ISP and VPN status are not in registry
 * delegation data and cannot be derived from it; `laranail/ip-intel` is where
 * those live, and it uses this as its offline tier.
 */
final readonly class IpController
{
    public function __construct(
        private AtlasService $atlas,
    ) {}

    public function __invoke(string $ip): JsonResponse
    {
        $address = IpAddress::parse($ip);

        if (! $address instanceof IpAddress) {
            // Validated here rather than in a FormRequest because the address
            // is a path segment: a route-model-binding style 404 would say the
            // endpoint does not exist, and 422 is what a malformed argument is.
            return new JsonResponse([
                'message' => 'That is not an IP address.',
                'errors' => ['ip' => ['That is not an IP address.']],
            ], 422);
        }

        $country = $this->atlas->countryForIp($address);

        if (! $country instanceof CountryRecord) {
            // 200, not 404. The question was well formed and the answer is
            // "nowhere" — a reserved address, a registry gap, or an uninstalled
            // table. `reason` separates the deployment problem from the other
            // two, because one is fixable here and the others are just how the
            // internet is.
            return new JsonResponse([
                'data' => null,
                'reason' => match (true) {
                    $address->isReserved() => 'reserved',
                    $this->atlas->describe()['ip_ready'] === false => 'table_not_installed',
                    default => 'not_allocated',
                },
            ]);
        }

        return new JsonResponse(['data' => new CountryResource($country)->toArray(request())]);
    }
}
