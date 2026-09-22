<?php

declare(strict_types=1);

namespace Gorshkov\CatalogSentinel\Domain\Rule\Rules;

use Gorshkov\CatalogSentinel\Domain\Analysis\MetricNames;
use Gorshkov\CatalogSentinel\Domain\Rule\RuleContext;
use Gorshkov\CatalogSentinel\Domain\Rule\RuleInterface;
use Gorshkov\CatalogSentinel\Domain\Rule\RuleOutcome;
use Gorshkov\CatalogSentinel\Domain\Rule\RuleResult;

final class IdentifierMissingRatioRule implements RuleInterface
{
    use RuleSupport;

    public const CODE = 'identifier.missing_ratio';

    public function code(): string
    {
        return self::CODE;
    }

    public function evaluate(RuleContext $context): RuleResult
    {
        if (($result = $this->disabled($context)) !== null) {
            return $result;
        }
        $total = $context->analysis->metrics->get(MetricNames::OBJECTS_TOTAL);
        $missing = $context->analysis->metrics->get(MetricNames::OBJECTS_MISSING_ID);
        $config = $this->config($context);
        if ($total === null || $missing === null || $total < (int) $config['min_objects']) {
            return new RuleResult(self::CODE, RuleOutcome::NOT_APPLICABLE, 'metric.not_applicable', evaluatedAt: $context->evaluatedAt);
        }
        $ratio = $total > 0 ? (float) $missing / (float) $total : 0.0;
        $triggered = $missing >= (int) $config['min_missing'] && $ratio >= (float) $config['min_ratio'];

        return new RuleResult(
            self::CODE,
            $triggered ? $this->outcomeFromAction((string) $config['action']) : RuleOutcome::PASS,
            $triggered ? 'identifier.missing_ratio.triggered' : 'identifier.missing_ratio.pass',
            ['objects_total' => $total, 'objects_missing_id' => $missing, 'ratio' => $ratio],
            [
                'min_objects' => (int) $config['min_objects'],
                'min_missing' => (int) $config['min_missing'],
                'min_ratio' => (float) $config['min_ratio'],
            ],
            sampleIds: $context->analysis->samples['missing_id'] ?? [],
            evaluatedAt: $context->evaluatedAt,
        );
    }
}
