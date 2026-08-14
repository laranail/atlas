<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Atlas\Tests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Simtabi\Laranail\Atlas\Providers\AtlasServiceProvider;

/**
 * Base case for tests that need a booted Laravel application.
 *
 * Unit tests under `tests/Unit` deliberately do **not** use this: `src/Core` is
 * framework-free, and testing it without a container is the cheapest possible
 * proof that it stayed that way. deptrac enforces the same boundary statically;
 * a unit test that suddenly needs a container is the runtime signal.
 */
abstract class TestCase extends OrchestraTestCase
{
    /**
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [AtlasServiceProvider::class];
    }
}
