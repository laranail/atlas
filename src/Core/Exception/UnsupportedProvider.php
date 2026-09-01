<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Atlas\Core\Exception;

use RuntimeException;
use Simtabi\Laranail\Atlas\Core\Contracts\AtlasException;

/**
 * A data source was named that cannot be resolved.
 *
 * Three distinct failures, kept apart because the fix differs each time:
 * the name is not in the allow-list, the source is known but its data package
 * is not installed, or the source is declared and not yet implemented.
 */
final class UnsupportedProvider extends RuntimeException implements AtlasException
{
    /**
     * @param  list<string>  $known
     */
    public static function unknown(string $name, array $known): self
    {
        return new self(sprintf(
            'Unknown atlas provider [%s]. Available: %s. Register another with AtlasManager::extend(), which takes a closure.',
            $name,
            implode(', ', $known),
        ));
    }

    public static function missingPackage(string $name, string $package): self
    {
        return new self(sprintf(
            'The [%s] atlas provider needs %s, which is not installed. Run `composer require %s`, or set '
            .'ATLAS_PROVIDER=generated to use the dataset shipped with this package.',
            $name,
            $package,
            $package,
        ));
    }

    public static function notImplemented(string $name): self
    {
        return new self(sprintf(
            'The [%s] atlas provider is declared but not implemented yet. It does not fall back to another '
            .'source, because that would answer with data you did not choose.',
            $name,
        ));
    }
}
