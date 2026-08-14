<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Atlas\Facades;

use Closure;
use Illuminate\Support\Facades\Facade;
use Simtabi\Laranail\Atlas\Core\Contracts\PlaceRepository;
use Simtabi\Laranail\Atlas\Services\AtlasManager;

/**
 * @method static PlaceRepository repository(?string $name = null)
 * @method static AtlasManager extend(string $name, Closure $factory)
 * @method static list<string> available()
 *
 * @see AtlasManager
 */
final class Atlas extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return AtlasManager::class;
    }
}
