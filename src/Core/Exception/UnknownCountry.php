<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Atlas\Core\Exception;

use InvalidArgumentException;
use Simtabi\Laranail\Atlas\Core\Contracts\AtlasException;

final class UnknownCountry extends InvalidArgumentException implements AtlasException
{
    public static function code(string $code): self
    {
        return new self(sprintf(
            'No country matches [%s]. Expected an ISO 3166-1 alpha-2 (KE), alpha-3 (KEN) or numeric (404) code.',
            $code,
        ));
    }
}
