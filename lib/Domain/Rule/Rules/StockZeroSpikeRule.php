<?php

declare(strict_types=1);

namespace Gorshkov\CatalogSentinel\Domain\Rule\Rules;

use Gorshkov\CatalogSentinel\Domain\Analysis\DocumentKind;
use Gorshkov\CatalogSentinel\Domain\Analysis\MetricNames;
use Gorshkov\CatalogSentinel\Domain\Rule\RuleContext;
use Gorshkov\CatalogSentinel\Domain\Rule\RuleOutcome;
use Gorshkov\CatalogSentinel\Domain\Rule\RuleResult;

final class StockZeroSpikeRule extends AbstractZeroSpikeRule
{
    public const CODE = 'stock.zero_spike';

    public function code(): string
    {
        return self::CODE;
    }

    public function evaluate(RuleContext $context): RuleResult
    {
        if ($context->analysis->documentKind !== DocumentKind::OFFERS) {
            return new RuleResult(self::CODE, RuleOutcome::NOT_APPLICABLE, 'document.kind_not_applicable', evaluatedAt: $context->evaluatedAt);
        }

        return parent::evaluate($context);
    }

    protected function explicitMetric(): string
    {
        return MetricNames::STOCK_EXPLICIT;
    }

    protected function affectedMetric(): string
    {
        return MetricNames::STOCK_ZERO_OR_NEGATIVE;
    }

    protected function ratioMetric(): string
    {
        return MetricNames::STOCK_ZERO_RATIO;
    }

    protected function sampleCategory(): string
    {
        return 'stock_zero_or_negative';
    }
}
