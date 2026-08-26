<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Atlas\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Simtabi\Laranail\Atlas\Core\Contracts\PlaceRepository;

/**
 * An ISO 4217 code some country in the catalogue actually uses.
 *
 * Derived from the dataset rather than declared, so a currency that stops being
 * used stops validating without anybody editing a list. That is the point of
 * deriving it: `ZWL` and `SLL` both left circulation while every hardcoded
 * currency list in every application kept accepting them.
 */
final readonly class CurrencyCode implements ValidationRule
{
    public function __construct(
        private ?PlaceRepository $repository = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! in_array(strtoupper(trim($value)), $this->codes(), true)) {
            $fail('laranail/atlas::validation.currency_code')->translate(['attribute' => $attribute]);
        }
    }

    /**
     * @return list<string>
     */
    private function codes(): array
    {
        $codes = [];

        foreach ($this->repository()->all() as $country) {
            foreach ($country->currencies as $code) {
                $codes[strtoupper($code)] = true;
            }
        }

        return array_keys($codes);
    }

    private function repository(): PlaceRepository
    {
        return $this->repository ?? app(PlaceRepository::class);
    }
}
