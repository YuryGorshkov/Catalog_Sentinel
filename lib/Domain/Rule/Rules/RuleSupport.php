<?php

declare(strict_types=1);

namespace Gorshkov\CatalogSentinel\Domain\Rule\Rules;

use Gorshkov\CatalogSentinel\Domain\Rule\RuleContext;
use Gorshkov\CatalogSentinel\Domain\Rule\RuleOutcome;
use Gorshkov\CatalogSentinel\Domain\Rule\RuleResult;

trait RuleSupport
{
    /** @return array<string, bool|int|float|string> */
    private function config(RuleContext $context): array
    {
        return $context->policy->rule($this->code());
    }

    private function disabled(RuleContext $context): ?RuleResult
    {
        $config = $this->config($context);
        if (($config['enabled'] ?? false) === true) {
            return null;
        }

        return new RuleResult(
            $this->code(),
            RuleOutcome::NOT_APPLICABLE,
            'rule.disabled',
            evaluatedAt: $context->evaluatedAt,
        );
    }

    private function baselineUnavailable(RuleContext $context): ?RuleResult
    {
        if ($context->baseline->isReadyFor($context->policy->analyzerSchemaVersion)) {
            return null;
        }

        return new RuleResult(
            $this->code(),
            RuleOutcome::NOT_APPLICABLE,
            'baseline.not_ready',
            baseline: [
                'sample_count' => $context->baseline->sampleCount,
                'minimum_samples' => $context->baseline->minimumSamples,
                'schema_version' => $context->baseline->analyzerSchemaVersion,
            ],
            evaluatedAt: $context->evaluatedAt,
        );
    }

    private function outcomeFromAction(string $action): string
    {
        return $action === RuleOutcome::BLOCK ? RuleOutcome::BLOCK : RuleOutcome::WARN;
    }
}
