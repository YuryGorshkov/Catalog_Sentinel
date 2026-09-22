<?php

declare(strict_types=1);

namespace Gorshkov\CatalogSentinel\Application\Baseline;

use Gorshkov\CatalogSentinel\Domain\Analysis\MetricNames;

final class BaselineMetricFilter
{
    private const EXACT = [
        MetricNames::OBJECTS_TOTAL,
        MetricNames::BYTES,
        MetricNames::STOCK_ZERO_RATIO,
        MetricNames::PRICE_ZERO_RATIO,
    ];

    /**
     * @param array<string, int|float> $metrics
     * @return array<string, int|float>
     */
    public function filter(array $metrics): array
    {
        return array_filter(
            $metrics,
            static fn (int|float $value, string $name): bool => in_array($name, self::EXACT, true)
                || (str_starts_with($name, 'property.') && str_ends_with($name, '.empty_ratio')),
            ARRAY_FILTER_USE_BOTH,
        );
    }
}
