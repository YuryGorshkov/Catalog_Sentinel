<?php

declare(strict_types=1);

namespace Gorshkov\CatalogSentinel\Domain\Rule\Rules;

use Gorshkov\CatalogSentinel\Domain\Analysis\MetricNames;
use Gorshkov\CatalogSentinel\Domain\Rule\RuleContext;
use Gorshkov\CatalogSentinel\Domain\Rule\RuleInterface;
use Gorshkov\CatalogSentinel\Domain\Rule\RuleOutcome;
use Gorshkov\CatalogSentinel\Domain\Rule\RuleResult;

final class FileSizeDropRule implements RuleInterface
{
    use RuleSupport;

    public const CODE = 'file.size_drop';

    public function code(): string
    {
        return self::CODE;
    }

    public function evaluate(RuleContext $context): RuleResult
    {
        if (($result = $this->disabled($context)) !== null || ($result = $this->baselineUnavailable($context)) !== null) {
            return $result;
        }
        $current = $context->analysis->metrics->get(MetricNames::BYTES);
        $median = $context->baseline->median(MetricNames::BYTES);
        $config = $this->config($context);
        if ($current === null || $median === null || $median < (float) $config['min_baseline_bytes']) {
            return new RuleResult(self::CODE, RuleOutcome::NOT_APPLICABLE, 'metric.not_applicable', evaluatedAt: $context->evaluatedAt);
        }
        $ratio = $median > 0.0 ? (float) $current / $median : 0.0;
        $triggered = $ratio <= (float) $config['max_ratio'];

        return new RuleResult(
            self::CODE,
            $triggered ? $this->outcomeFromAction((string) $config['action']) : RuleOutcome::PASS,
            $triggered ? 'file.size_drop.triggered' : 'file.size_drop.pass',
            ['bytes' => $current, 'ratio' => $ratio],
            ['max_ratio' => (float) $config['max_ratio'], 'min_baseline_bytes' => (int) $config['min_baseline_bytes']],
            ['median_bytes' => $median, 'sample_count' => $context->baseline->sampleCount],
            evaluatedAt: $context->evaluatedAt,
        );
    }
}
