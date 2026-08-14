<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Atlas\Core\Contracts;

use Throwable;

/**
 * Marks every exception this package throws.
 *
 * A consumer can `catch (AtlasException $e)` without naming the concrete types,
 * and without catching a `RuntimeException` that came from somewhere else in
 * their stack. Nothing here extends a common base class: the concrete
 * exceptions extend whichever SPL type describes the failure honestly
 * (`InvalidArgumentException` for bad input, `RuntimeException` for a missing
 * dataset), and this interface is what ties them together.
 */
interface AtlasException extends Throwable {}
