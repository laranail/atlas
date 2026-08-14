<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Atlas\Bridges\Chrono;

use Simtabi\Laranail\Atlas\Enums\Country;

/**
 * The optional seam to `laranail/chrono`, which answers *when*.
 *
 * ## Why this is a bridge and not a dependency
 *
 * Atlas is `^8.4.1 || ^8.5`; chrono is `^8.5`. Requiring it would drag every
 * atlas consumer to the higher floor to gain timezone lookups most of them
 * never ask for. So chrono is a `suggest`, and this class is the only place in
 * the package that names it.
 *
 * That also keeps `src/Core` honest: deptrac forbids Core from referencing any
 * other `Simtabi\Laranail\*` package, and this lives outside Core precisely so
 * the rule stays absolute rather than gaining an exception.
 *
 * ## Absent means absent, not broken
 *
 * Without chrono installed, {@see isAvailable()} is false and the accessors
 * throw a message naming the package to install — not a `Class not found`
 * three frames deeper, and not a silent null that reads as "this country has no
 * timezones".
 */
final readonly class ChronoBridge
{
    /**
     * The facade this bridges to. Named as a string rather than imported: an
     * import of a class that may not exist is harmless at runtime but reads as
     * a hard dependency to a human and to static analysis.
     */
    private const string TIMEZONES = 'Simtabi\\Laranail\\Chrono\\Facades\\Timezones';

    public function __construct(
        private bool $enabled = true,
    ) {}

    /**
     * Whether country-to-timezone questions can be answered right now.
     *
     * False for either reason — chrono absent, or the bridge switched off in
     * config — because a caller's next move is the same in both cases.
     */
    public function isAvailable(): bool
    {
        return $this->enabled && class_exists(self::TIMEZONES);
    }

    /**
     * The IANA identifiers a country spans.
     *
     * @return list<string>
     */
    public function timezonesFor(Country|string $country): array
    {
        $this->assertAvailable();

        $code = $country instanceof Country ? $country->value : strtoupper(trim($country));

        // chrono's TimezoneCollection exposes identifiers() directly, so this
        // takes the list rather than reaching into the value objects inside it.
        // An earlier draft walked the collection and tried identifier(), then a
        // `name` property, then a string cast — a chain of guesses about a class
        // this file deliberately does not import, and the last of those is not
        // even safe on an arbitrary object.
        $identifiers = self::TIMEZONES::inCountry($code)->identifiers();

        return array_values(array_filter(
            $identifiers,
            static fn (mixed $identifier): bool => is_string($identifier) && $identifier !== '',
        ));
    }

    /**
     * The first timezone a country spans, or null when it spans none.
     *
     * "First" is chrono's own ordering. For the 200-odd countries in a single
     * zone this is simply *the* zone; for the handful that span several — the
     * US, Russia, Australia — picking one is arbitrary and the caller should use
     * {@see timezonesFor()} instead. Said here rather than discovered later.
     */
    public function primaryTimezoneFor(Country|string $country): ?string
    {
        return $this->timezonesFor($country)[0] ?? null;
    }

    private function assertAvailable(): void
    {
        if ($this->isAvailable()) {
            return;
        }

        throw class_exists(self::TIMEZONES)
            ? ChronoBridgeUnavailable::disabled()
            : ChronoBridgeUnavailable::notInstalled();
    }
}
