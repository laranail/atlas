<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Atlas\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Simtabi\Laranail\Atlas\Core\Contracts\PlaceRepository;
use Simtabi\Laranail\Atlas\Core\Country\CountryRecord;

/**
 * A country code this catalogue actually holds.
 *
 * Not `size:2`, and not `in:` against a list pasted into a request class. Both
 * accept `XX`, `ZZ`, `UK` and every other well-shaped string that names no
 * country — and the pasted list starts drifting from the dataset the moment
 * either changes. This asks the repository, so it stays true when the dataset
 * is regenerated and when the data source is swapped.
 *
 * Alpha-2, alpha-3 and numeric are all accepted, because a form that takes one
 * and an import that takes another should not need two different rules.
 */
final readonly class CountryCode implements ValidationRule
{
    public function __construct(
        private ?PlaceRepository $repository = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! $this->repository()->find($value) instanceof CountryRecord) {
            $fail('laranail-atlas::validation.country_code')->translate(['attribute' => $attribute]);
        }
    }

    /**
     * Resolved lazily rather than required in the constructor.
     *
     * `new CountryCode` inside a `rules()` array is the idiom, and a rule
     * object is often built before anything wants to touch the container —
     * requiring a repository up front would make the ergonomic form the
     * awkward one. Passing an instance still works, which is what a unit test
     * without a container wants.
     */
    private function repository(): PlaceRepository
    {
        return $this->repository ?? app(PlaceRepository::class);
    }
}
