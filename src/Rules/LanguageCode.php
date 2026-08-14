<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Atlas\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Simtabi\Laranail\Atlas\Core\Contracts\PlaceRepository;

/**
 * An ISO 639 code spoken in some country in the catalogue.
 *
 * The bound is deliberately that, not "every language ISO 639 lists": this
 * package's dataset carries the languages attached to countries, so a code it
 * has never seen cannot be enriched with anything and accepting it would mean
 * storing a value nothing downstream can resolve.
 */
final readonly class LanguageCode implements ValidationRule
{
    public function __construct(
        private ?PlaceRepository $repository = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! in_array(strtolower(trim($value)), $this->codes(), true)) {
            $fail('laranail-atlas::validation.language_code')->translate(['attribute' => $attribute]);
        }
    }

    /**
     * @return list<string>
     */
    private function codes(): array
    {
        $codes = [];

        foreach ($this->repository()->all() as $country) {
            foreach ($country->languages as $code) {
                $codes[strtolower($code)] = true;
            }
        }

        return array_keys($codes);
    }

    private function repository(): PlaceRepository
    {
        return $this->repository ?? app(PlaceRepository::class);
    }
}
