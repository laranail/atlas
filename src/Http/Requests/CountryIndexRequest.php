<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Atlas\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Simtabi\Laranail\Atlas\Core\Country\CountryQuery;
use Simtabi\Laranail\Atlas\Rules\CurrencyCode;
use Simtabi\Laranail\Atlas\Rules\LanguageCode;

/**
 * The filters `GET /countries` accepts.
 *
 * Every one maps to a {@see CountryQuery} method, and the request builds the
 * query itself rather than handing the controller an array to interpret. That
 * keeps the parameter names and the builder in one file: adding a filter here
 * without a matching method, or renaming a method without touching this, both
 * become impossible rather than merely discouraged.
 */
final class CountryIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorisation belongs to the middleware the host configures. A
        // package cannot know who may call this, and returning false would
        // make the endpoint unusable rather than secure.
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'continent' => ['sometimes', 'string', 'max:32'],
            'region' => ['sometimes', 'string', 'max:64'],
            'subregion' => ['sometimes', 'string', 'max:64'],
            'currency' => ['sometimes', 'string', new CurrencyCode],
            'language' => ['sometimes', 'string', new LanguageCode],
            'search' => ['sometimes', 'string', 'max:64'],
            'inhabited' => ['sometimes', 'boolean'],
            // Bounded, and low. This endpoint is cacheable and read-only, but
            // an unbounded limit still lets one request serialise all 250
            // records repeatedly — and nobody paginating wants 250 at a time.
            'limit' => ['sometimes', 'integer', 'min:1', 'max:250'],
        ];
    }

    /**
     * Apply the validated filters to a query.
     */
    public function applyTo(CountryQuery $query): CountryQuery
    {
        if (is_string($continent = $this->validated('continent'))) {
            $query = $query->inContinent($continent);
        }

        if (is_string($region = $this->validated('region'))) {
            $query = $query->inRegion($region);
        }

        if (is_string($subregion = $this->validated('subregion'))) {
            $query = $query->inSubregion($subregion);
        }

        if (is_string($currency = $this->validated('currency'))) {
            $query = $query->usingCurrency($currency);
        }

        if (is_string($language = $this->validated('language'))) {
            $query = $query->speakingLanguage($language);
        }

        if (is_string($search = $this->validated('search'))) {
            $query = $query->whereNameContains($search);
        }

        if ($this->has('inhabited') && $this->boolean('inhabited')) {
            $query = $query->inhabitedOnly();
        }

        $query = $query->sortedByName();

        // Cast rather than `is_int`. A query string is all strings, and the
        // `integer` rule validates without converting — so `is_int('5')` is
        // false and `?limit=5` silently returned all 250 records.
        if ($this->has('limit')) {
            return $query->take((int) $this->validated('limit'));
        }

        return $query;
    }
}
