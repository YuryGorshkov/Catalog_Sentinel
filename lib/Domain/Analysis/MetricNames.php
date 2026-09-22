<?php

declare(strict_types=1);

namespace Gorshkov\CatalogSentinel\Domain\Analysis;

final class MetricNames
{
    public const OBJECTS_TOTAL = 'document.objects_total';
    public const OBJECTS_WITH_ID = 'document.objects_with_id';
    public const OBJECTS_MISSING_ID = 'document.objects_missing_id';
    public const VALUE_ERRORS = 'document.value_errors';
    public const BYTES = 'document.bytes';
    public const DURATION_MS = 'document.duration_ms';

    public const STOCK_EXPLICIT = 'stock.objects_with_explicit_value';
    public const STOCK_ZERO_OR_NEGATIVE = 'stock.objects_zero_or_negative';
    public const STOCK_POSITIVE = 'stock.objects_positive';
    public const STOCK_ZERO_RATIO = 'stock.zero_ratio';
    public const STOCK_NEGATIVE_VALUES = 'stock.negative_values';

    public const PRICE_EXPLICIT = 'price.objects_with_explicit_value';
    public const PRICE_ALL_ZERO = 'price.objects_all_explicit_zero';
    public const PRICE_POSITIVE = 'price.objects_with_positive_value';
    public const PRICE_ZERO_RATIO = 'price.zero_ratio';
    public const PRICE_VALUES_TOTAL = 'price.values_total';

    private function __construct()
    {
    }

    public static function property(string $externalId, string $metric): string
    {
        return 'property.' . hash('sha256', trim($externalId)) . '.' . $metric;
    }
}
