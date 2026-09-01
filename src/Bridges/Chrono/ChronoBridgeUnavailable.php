<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Atlas\Bridges\Chrono;

use RuntimeException;
use Simtabi\Laranail\Atlas\Core\Contracts\AtlasException;

/**
 * Country-to-timezone was asked for and cannot be answered.
 *
 * Two distinct causes, kept apart because the fix differs: the package is not
 * installed, or it is installed and the bridge is switched off. A single "not
 * available" would send someone to install what they already have.
 */
final class ChronoBridgeUnavailable extends RuntimeException implements AtlasException
{
    public static function notInstalled(): self
    {
        return new self(
            'Country-to-timezone lookups need laranail/chrono, which is not installed. Run '
            .'`composer require laranail/chrono`. It is a suggest rather than a dependency because '
            .'its PHP floor is ^8.5 and this package supports ^8.4.1.',
        );
    }

    public static function disabled(): self
    {
        return new self(
            'laranail/chrono is installed but the bridge is off. Set laranail.atlas.chrono.enabled '
            .'to true to use country-to-timezone lookups.',
        );
    }
}
