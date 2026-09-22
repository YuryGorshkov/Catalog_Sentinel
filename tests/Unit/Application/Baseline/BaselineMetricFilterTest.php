<?php

declare(strict_types=1);

namespace Gorshkov\CatalogSentinel\Tests\Unit\Application\Baseline;

use Gorshkov\CatalogSentinel\Application\Baseline\BaselineMetricFilter;
use Gorshkov\CatalogSentinel\Domain\Analysis\MetricNames;
use PHPUnit\Framework\TestCase;

final class BaselineMetricFilterTest extends TestCase
{
    public function testOnlyComparableMetricsAreStored(): void
    {
        $propertyRatio = MetricNames::property('COLOR', 'empty_ratio');
        $filtered = (new BaselineMetricFilter())->filter([
            MetricNames::OBJECTS_TOTAL => 100,
            MetricNames::BYTES => 1000,
            MetricNames::STOCK_ZERO_RATIO => 0.1,
            MetricNames::PRICE_ZERO_RATIO => 0.2,
            MetricNames::DURATION_MS => 99,
            MetricNames::VALUE_ERRORS => 3,
            $propertyRatio => 0.4,
        ]);

        self::assertSame([
            MetricNames::OBJECTS_TOTAL => 100,
            MetricNames::BYTES => 1000,
            MetricNames::STOCK_ZERO_RATIO => 0.1,
            MetricNames::PRICE_ZERO_RATIO => 0.2,
            $propertyRatio => 0.4,
        ], $filtered);
    }
}
