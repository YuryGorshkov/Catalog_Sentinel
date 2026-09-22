<?php

declare(strict_types=1);

namespace Gorshkov\CatalogSentinel\Infrastructure\Xml;

use Gorshkov\CatalogSentinel\Domain\Analysis\MetricNames;

final class MetricAccumulator
{
    /** @var array<string, int|float> */
    private array $metrics = [
        MetricNames::OBJECTS_TOTAL => 0,
        MetricNames::OBJECTS_WITH_ID => 0,
        MetricNames::OBJECTS_MISSING_ID => 0,
        MetricNames::VALUE_ERRORS => 0,
        MetricNames::STOCK_EXPLICIT => 0,
        MetricNames::STOCK_ZERO_OR_NEGATIVE => 0,
        MetricNames::STOCK_POSITIVE => 0,
        MetricNames::STOCK_NEGATIVE_VALUES => 0,
        MetricNames::PRICE_EXPLICIT => 0,
        MetricNames::PRICE_ALL_ZERO => 0,
        MetricNames::PRICE_POSITIVE => 0,
        MetricNames::PRICE_VALUES_TOTAL => 0,
    ];

    /** @param list<string> $protectedProperties */
    public function __construct(
        private readonly array $protectedProperties,
        private readonly SampleCollector $samples,
    ) {
        foreach ($protectedProperties as $externalId) {
            $this->metrics[MetricNames::property($externalId, 'objects_with_explicit_value')] = 0;
            $this->metrics[MetricNames::property($externalId, 'objects_explicit_empty')] = 0;
            $this->metrics[MetricNames::property($externalId, 'objects_non_empty')] = 0;
        }
    }

    public function addValueError(CurrentItemState $item): void
    {
        ++$this->metrics[MetricNames::VALUE_ERRORS];
        ++$item->numericErrors;
        $this->samples->add('value_error', $item->sampleId());
    }

    public function finish(CurrentItemState $item): void
    {
        ++$this->metrics[MetricNames::OBJECTS_TOTAL];
        if ($item->externalId === null || $item->externalId === '') {
            ++$this->metrics[MetricNames::OBJECTS_MISSING_ID];
            $this->samples->add('missing_id', '#' . $item->ordinal);
        } else {
            ++$this->metrics[MetricNames::OBJECTS_WITH_ID];
        }

        $stockValues = $item->warehouseStockValues !== []
            ? $item->warehouseStockValues
            : $item->generalStockValues;
        if ($stockValues !== []) {
            ++$this->metrics[MetricNames::STOCK_EXPLICIT];
            $total = array_sum($stockValues);
            foreach ($stockValues as $value) {
                if ($value < 0.0) {
                    ++$this->metrics[MetricNames::STOCK_NEGATIVE_VALUES];
                }
            }
            if ($total <= 0.0) {
                ++$this->metrics[MetricNames::STOCK_ZERO_OR_NEGATIVE];
                $this->samples->add('stock_zero_or_negative', $item->sampleId());
            } else {
                ++$this->metrics[MetricNames::STOCK_POSITIVE];
            }
        }

        if ($item->priceValues !== []) {
            ++$this->metrics[MetricNames::PRICE_EXPLICIT];
            $this->metrics[MetricNames::PRICE_VALUES_TOTAL] += count($item->priceValues);
            $allZeroOrNegative = true;
            foreach ($item->priceValues as $price) {
                if ($price > 0.0) {
                    $allZeroOrNegative = false;
                    break;
                }
            }
            if ($allZeroOrNegative) {
                ++$this->metrics[MetricNames::PRICE_ALL_ZERO];
                $this->samples->add('price_all_zero', $item->sampleId());
            } else {
                ++$this->metrics[MetricNames::PRICE_POSITIVE];
            }
        }

        foreach ($this->protectedProperties as $externalId) {
            if (!array_key_exists($externalId, $item->properties)) {
                continue;
            }
            ++$this->metrics[MetricNames::property($externalId, 'objects_with_explicit_value')];
            if ($item->properties[$externalId]) {
                ++$this->metrics[MetricNames::property($externalId, 'objects_non_empty')];
            } else {
                ++$this->metrics[MetricNames::property($externalId, 'objects_explicit_empty')];
                $this->samples->add('property_empty.' . hash('sha256', $externalId), $item->sampleId());
            }
        }
    }

    /** @return array<string, int|float> */
    public function finalize(int $bytes, int $durationMs): array
    {
        $this->metrics[MetricNames::BYTES] = $bytes;
        $this->metrics[MetricNames::DURATION_MS] = $durationMs;
        $stockExplicit = $this->metrics[MetricNames::STOCK_EXPLICIT];
        $priceExplicit = $this->metrics[MetricNames::PRICE_EXPLICIT];
        $this->metrics[MetricNames::STOCK_ZERO_RATIO] = $stockExplicit > 0
            ? $this->metrics[MetricNames::STOCK_ZERO_OR_NEGATIVE] / $stockExplicit
            : 0.0;
        $this->metrics[MetricNames::PRICE_ZERO_RATIO] = $priceExplicit > 0
            ? $this->metrics[MetricNames::PRICE_ALL_ZERO] / $priceExplicit
            : 0.0;
        foreach ($this->protectedProperties as $externalId) {
            $explicitKey = MetricNames::property($externalId, 'objects_with_explicit_value');
            $emptyKey = MetricNames::property($externalId, 'objects_explicit_empty');
            $ratioKey = MetricNames::property($externalId, 'empty_ratio');
            $explicit = $this->metrics[$explicitKey];
            $this->metrics[$ratioKey] = $explicit > 0 ? $this->metrics[$emptyKey] / $explicit : 0.0;
        }

        return $this->metrics;
    }
}
