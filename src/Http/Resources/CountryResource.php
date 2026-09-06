<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Atlas\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Simtabi\Laranail\Atlas\Core\Country\CountryRecord;

/**
 * One country, as the API renders it.
 *
 * A resource rather than `->toArray()` straight off the record, so the wire
 * shape is a decision this file owns. `CountryRecord` is a domain type and may
 * gain fields; an endpoint that returned whatever it happened to hold would
 * publish each of those the moment it was added, and there would be no version
 * of the API where that was reviewed.
 *
 * @mixin CountryRecord
 */
final class CountryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var CountryRecord $country */
        $country = $this->resource;

        return [
            'iso2'          => $country->iso2,
            'iso3'          => $country->iso3,
            'numeric'       => $country->numeric,
            'name'          => $country->name,
            'official_name' => $country->officialName,
            'native_name'   => $country->nativeName,
            'continent'     => $country->continent,
            'region'        => $country->region,
            'subregion'     => $country->subregion,
            'flag'          => $country->flag(),
            'tld'           => $country->tld,
            'coordinates'   => $country->coordinates?->toArray(),
            'bounds'        => $country->bounds?->toBbox(),
            'currencies'    => $country->currencies,
            'currency'      => $country->currency(),
            'languages'     => $country->languages,
            'calling_codes' => $country->callingCodes,
            'calling_code'  => $country->callingCode(),
        ];
    }
}
