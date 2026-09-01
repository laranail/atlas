<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Atlas\Core\Geo;

use JsonSerializable;
use Simtabi\Laranail\Atlas\Core\Exception\InvalidCoordinates;
use Stringable;

/**
 * A length, carrying its own unit.
 *
 * The helper this replaces returned a bare `float` whose unit was decided by a
 * string argument several lines earlier, so `$d > 100` was unreadable without
 * scrolling and a unit change at the call site silently rescaled every
 * comparison below it. A distance that knows what it is cannot be compared
 * against the wrong number by accident.
 *
 * Stored in metres regardless of how it was constructed, so two distances built
 * in different units compare correctly.
 */
final readonly class Distance implements JsonSerializable, Stringable
{
    private function __construct(
        public float $metres,
    ) {}

    public function __toString(): string
    {
        return $this->format();
    }

    public static function fromMetres(float $metres): self
    {
        if (is_nan($metres) || is_infinite($metres) || $metres < 0.0) {
            throw InvalidCoordinates::negativeDistance($metres);
        }

        return new self($metres);
    }

    public static function from(float $value, DistanceUnit $unit): self
    {
        return self::fromMetres($value * $unit->inMetres());
    }

    public function in(DistanceUnit $unit): float
    {
        return $this->metres / $unit->inMetres();
    }

    public function kilometres(): float
    {
        return $this->in(DistanceUnit::Kilometres);
    }

    public function miles(): float
    {
        return $this->in(DistanceUnit::Miles);
    }

    public function nauticalMiles(): float
    {
        return $this->in(DistanceUnit::NauticalMiles);
    }

    public function isShorterThan(self $other): bool
    {
        return $this->metres < $other->metres;
    }

    public function isLongerThan(self $other): bool
    {
        return $this->metres > $other->metres;
    }

    /**
     * Rounded, in the given unit, for display.
     */
    public function format(DistanceUnit $unit = DistanceUnit::Kilometres, int $precision = 1): string
    {
        return sprintf('%s %s', round($this->in($unit), $precision), $unit->value);
    }

    /**
     * @return array{metres: float, kilometres: float, miles: float}
     */
    public function toArray(): array
    {
        return [
            'metres' => $this->metres,
            'kilometres' => $this->kilometres(),
            'miles' => $this->miles(),
        ];
    }

    /**
     * @return array{metres: float, kilometres: float, miles: float}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
