<?php

declare(strict_types=1);

namespace Gorshkov\CatalogSentinel\Domain\Rule\Rules;

use Gorshkov\CatalogSentinel\Domain\Analysis\MetricNames;
use Gorshkov\CatalogSentinel\Domain\Rule\RuleContext;
use Gorshkov\CatalogSentinel\Domain\Rule\RuleInterface;
use Gorshkov\CatalogSentinel\Domain\Rule\RuleOutcome;
use Gorshkov\CatalogSentinel\Domain\Rule\RuleResult;

final class ItemCountDropRule implements RuleInterface
{
    use RuleSupport;

    public const CODE = 'document.item_count_drop';

    public function code(): string
    {
        return self::CODE;
    }

    public function evaluate(RuleContext $context): RuleResult
    {
        if (($result = $this->disabled($context)) !== null || ($result = $this->baselineUnavailable($context)) !== null) {
            return $result;
        }
        $current = $context->analysis->metrics->get(MetricNames::OBJECTS_TOTAL);
        $median = $context->baseline->median(MetricNames::OBJECTS_TOTAL);
        $config = $this->config($context);
        if ($current === null || $median === null || $median < (float) $config['min_baseline_objects']) {
            return new RuleResult(self::CODE, RuleOutcome::NOT_APPLICABLE, 'metric.not_applicable', evaluatedAt: $context->evaluatedAt);
        }
        $ratio = $median > 0.0 ? (float) $current / $median : 0.0;
        $triggered = $ratio <= (float) $config['max_ratio'];

        return new RuleResult(
            self::CODE,
            $triggered ? $this->outcomeFromAction((string) $config['action']) : RuleOutcome::PASS,
            $triggered ? 'document.item_count_drop.triggered' : 'document.item_count_drop.pass',
            ['objects_total' => $current, 'ratio' => $ratio],
            ['max_ratio' => (float) $config['max_ratio'], 'min_baseline_objects' => (int) $config['min_baseline_objects']],
            ['median_objects_total' => $median, 'sample_count' => $context->baseline->sampleCount],
            evaluatedAt: $context->evaluatedAt,
        );
    }
}
