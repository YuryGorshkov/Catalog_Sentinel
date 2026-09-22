<?php

declare(strict_types=1);

namespace Gorshkov\CatalogSentinel\Domain\Rule\Rules;

use Gorshkov\CatalogSentinel\Domain\Analysis\DocumentKind;
use Gorshkov\CatalogSentinel\Domain\Analysis\MetricNames;
use Gorshkov\CatalogSentinel\Domain\Rule\RuleContext;
use Gorshkov\CatalogSentinel\Domain\Rule\RuleInterface;
use Gorshkov\CatalogSentinel\Domain\Rule\RuleOutcome;
use Gorshkov\CatalogSentinel\Domain\Rule\RuleResult;

final class PropertyEmptySpikeRule implements RuleInterface
{
    use RuleSupport;

    public const CODE = 'property.empty_spike';

    public function code(): string
    {
        return self::CODE;
    }

    public function evaluate(RuleContext $context): RuleResult
    {
        if (($result = $this->disabled($context)) !== null || ($result = $this->baselineUnavailable($context)) !== null) {
            return $result;
        }
        if ($context->analysis->documentKind !== DocumentKind::CATALOG || $context->policy->protectedProperties === []) {
            return new RuleResult(self::CODE, RuleOutcome::NOT_APPLICABLE, 'property.not_configured', evaluatedAt: $context->evaluatedAt);
        }

        $config = $this->config($context);
        $triggered = [];
        $actual = [];
        $baseline = [];
        $samples = [];
        foreach ($context->policy->protectedProperties as $externalId) {
            $explicitKey = MetricNames::property($externalId, 'objects_with_explicit_value');
            $emptyKey = MetricNames::property($externalId, 'objects_explicit_empty');
            $ratioKey = MetricNames::property($externalId, 'empty_ratio');
            $explicit = $context->analysis->metrics->get($explicitKey);
            $empty = $context->analysis->metrics->get($emptyKey);
            $ratio = $context->analysis->metrics->get($ratioKey);
            $median = $context->baseline->median($ratioKey);
            if ($explicit === null || $empty === null || $ratio === null || $median === null) {
                continue;
            }

            $delta = (float) $ratio - $median;
            $actual[$externalId . '.explicit'] = $explicit;
            $actual[$externalId . '.empty'] = $empty;
            $actual[$externalId . '.ratio'] = $ratio;
            $actual[$externalId . '.ratio_delta'] = $delta;
            $baseline[$externalId . '.median_ratio'] = $median;
            if (
                $explicit >= (int) $config['min_explicit']
                && $empty >= (int) $config['min_affected']
                && (float) $ratio >= (float) $config['min_current_ratio']
                && $delta >= (float) $config['min_ratio_delta']
            ) {
                $triggered[] = $externalId;
                $samples = array_merge($samples, $context->analysis->samples['property_empty.' . hash('sha256', $externalId)] ?? []);
            }
        }

        if ($actual === []) {
            return new RuleResult(self::CODE, RuleOutcome::NOT_APPLICABLE, 'metric.not_applicable', evaluatedAt: $context->evaluatedAt);
        }

        return new RuleResult(
            self::CODE,
            $triggered !== [] ? $this->outcomeFromAction((string) $config['action']) : RuleOutcome::PASS,
            $triggered !== [] ? 'property.empty_spike.triggered' : 'property.empty_spike.pass',
            $actual,
            [
                'min_current_ratio' => (float) $config['min_current_ratio'],
                'min_ratio_delta' => (float) $config['min_ratio_delta'],
                'min_affected' => (int) $config['min_affected'],
                'min_explicit' => (int) $config['min_explicit'],
            ],
            $baseline,
            array_slice(array_values(array_unique($samples)), 0, $context->policy->sampleLimit),
            ['properties' => implode(', ', $triggered)],
            $context->evaluatedAt,
        );
    }
}
