<?php

declare(strict_types=1);

namespace Gorshkov\CatalogSentinel\Domain\Rule\Rules;

use Gorshkov\CatalogSentinel\Domain\Rule\RuleContext;
use Gorshkov\CatalogSentinel\Domain\Rule\RuleInterface;
use Gorshkov\CatalogSentinel\Domain\Rule\RuleOutcome;
use Gorshkov\CatalogSentinel\Domain\Rule\RuleResult;

abstract class AbstractZeroSpikeRule implements RuleInterface
{
    use RuleSupport;

    abstract protected function explicitMetric(): string;

    abstract protected function affectedMetric(): string;

    abstract protected function ratioMetric(): string;

    abstract protected function sampleCategory(): string;

    public function evaluate(RuleContext $context): RuleResult
    {
        if (($result = $this->disabled($context)) !== null || ($result = $this->baselineUnavailable($context)) !== null) {
            return $result;
        }
        $metrics = $context->analysis->metrics;
        $explicit = $metrics->get($this->explicitMetric());
        $affected = $metrics->get($this->affectedMetric());
        $ratio = $metrics->get($this->ratioMetric());
        $median = $context->baseline->median($this->ratioMetric());
        $config = $this->config($context);
        if ($explicit === null || $affected === null || $ratio === null || $median === null) {
            return new RuleResult($this->code(), RuleOutcome::NOT_APPLICABLE, 'metric.not_applicable', evaluatedAt: $context->evaluatedAt);
        }
        if ($explicit < (int) $config['min_explicit']) {
            return new RuleResult(
                $this->code(),
                RuleOutcome::NOT_APPLICABLE,
                'metric.insufficient_explicit_values',
                ['explicit' => $explicit],
                ['min_explicit' => (int) $config['min_explicit']],
                evaluatedAt: $context->evaluatedAt,
            );
        }

        $delta = (float) $ratio - $median;
        $triggered = (float) $ratio >= (float) $config['min_current_ratio']
            && $delta >= (float) $config['min_ratio_delta']
            && $affected >= (int) $config['min_affected'];

        return new RuleResult(
            $this->code(),
            $triggered ? $this->outcomeFromAction((string) $config['action']) : RuleOutcome::PASS,
            $triggered ? $this->code() . '.triggered' : $this->code() . '.pass',
            ['explicit' => $explicit, 'affected' => $affected, 'ratio' => $ratio, 'ratio_delta' => $delta],
            [
                'min_current_ratio' => (float) $config['min_current_ratio'],
                'min_ratio_delta' => (float) $config['min_ratio_delta'],
                'min_affected' => (int) $config['min_affected'],
                'min_explicit' => (int) $config['min_explicit'],
            ],
            ['median_ratio' => $median, 'sample_count' => $context->baseline->sampleCount],
            $context->analysis->samples[$this->sampleCategory()] ?? [],
            evaluatedAt: $context->evaluatedAt,
        );
    }
}
