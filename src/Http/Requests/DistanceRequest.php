<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Atlas\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator as ValidatorFacade;
use Illuminate\Validation\Validator;
use Simtabi\Laranail\Atlas\Core\Geo\Coordinates;
use Simtabi\Laranail\Atlas\Rules\Coordinate;
use Simtabi\Laranail\Atlas\Rules\CountryCode;

/**
 * `GET /distance?from=&to=` — between two points, or between two countries.
 *
 * Both forms exist because both questions get asked, and the alternative is
 * two endpoints that differ only in how they turn their arguments into
 * coordinates. Each side is independently either a `lat,lon` pair or a country
 * code; mixing them is allowed and means what it looks like.
 */
final class DistanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'from' => ['required', 'string', 'max:64'],
            'to' => ['required', 'string', 'max:64'],
            'unit' => ['sometimes', 'string', 'in:km,mi,m,nmi'],
        ];
    }

    /**
     * Each side is validated as whichever form it is.
     *
     * Doing this in `after()` rather than as a rule on the field keeps the
     * error message specific: a caller who typed `51.5074,-0.1278x` is told
     * about a malformed coordinate, and one who typed `XX` about an unknown
     * country, rather than both getting "from is invalid".
     *
     * @return list<callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                foreach (['from', 'to'] as $field) {
                    $value = $this->input($field);

                    if (! is_string($value) || $value === '') {
                        continue;
                    }

                    $rule = str_contains($value, ',') ? new Coordinate : new CountryCode;

                    // Through a sub-validator rather than by calling the rule
                    // with a hand-rolled $fail. The rules end in
                    // `$fail(key)->translate([...])`, and only the framework's
                    // own PotentiallyTranslatedString resolves that — a stand-in
                    // that swallows translate() would add the raw translation
                    // key to the response as if it were the message.
                    $errors = ValidatorFacade::make([$field => $value], [$field => [$rule]])
                        ->errors()
                        ->get($field);

                    foreach ($errors as $message) {
                        // MessageBag::get() is typed as possibly returning
                        // nested arrays (it does, for wildcard attributes).
                        // This field has no wildcard, so the array arm is
                        // unreachable — flattening says so rather than casting
                        // it away.
                        foreach (Arr::flatten([$message]) as $line) {
                            $validator->errors()->add($field, (string) $line);
                        }
                    }
                }
            },
        ];
    }

    /**
     * Whichever form `from` took, as something the service can measure.
     */
    public function from(): Coordinates|string
    {
        return $this->point('from');
    }

    public function to(): Coordinates|string
    {
        return $this->point('to');
    }

    public function unit(): string
    {
        $unit = $this->validated('unit');

        return is_string($unit) ? $unit : 'km';
    }

    private function point(string $field): Coordinates|string
    {
        $value = (string) $this->validated($field);

        if (! str_contains($value, ',')) {
            return $value;
        }

        [$latitude, $longitude] = array_map(trim(...), explode(',', $value, 2));

        return new Coordinates((float) $latitude, (float) $longitude);
    }
}
