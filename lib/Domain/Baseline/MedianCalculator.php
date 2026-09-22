<?php

declare(strict_types=1);

namespace Gorshkov\CatalogSentinel\Domain\Baseline;

use InvalidArgumentException;

final class MedianCalculator
{
    /** @param list<int|float> $values */
    public function calculate(array $values): float
    {
        if ($values === []) {
            throw new InvalidArgumentException('Cannot calculate a median of an empty list.');
        }

        sort($values, SORT_NUMERIC);
        $count = count($values);
        $middle = intdiv($count, 2);
        if ($count % 2 === 1) {
            return (float) $values[$middle];
        }

        return ((float) $values[$middle - 1] + (float) $values[$middle]) / 2.0;
    }
}
