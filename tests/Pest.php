<?php

declare(strict_types=1);

use Simtabi\Laranail\Atlas\Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test case bindings
|--------------------------------------------------------------------------
|
| Only Feature tests boot Laravel. Unit tests exercise src/Core directly with
| no container, which is the cheapest proof that the core stayed framework-free
| — a stray Illuminate dependency there fails the test run, not just deptrac.
|
*/

uses(TestCase::class)->in('Feature');
