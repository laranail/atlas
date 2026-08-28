<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Atlas\Core\Contracts;

use Simtabi\Laranail\Atlas\Core\Geo\Distance;
use Simtabi\Laranail\Atlas\Core\Geo\Coordinates;

/**
 * How far apart two points are.
 *
 * A seam because there is no single right answer: a sphere is fast and accurate
 * to roughly 0.5%, an ellipsoid is accurate to millimetres and iterative, and
 * which one is correct depends entirely on the question. "Is the nearest branch
 * within 5 km" does not need the ellipsoid; a boundary survey does.
 */
interface DistanceCalculator
{
    public function between(Coordinates $from, Coordinates $to): Distance;

    /**
     * A short identifier, for config and for `doctor` output.
     */
    public function name(): string;
}
