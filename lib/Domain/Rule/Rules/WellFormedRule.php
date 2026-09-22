<?php

declare(strict_types=1);

namespace Gorshkov\CatalogSentinel\Domain\Rule\Rules;

use Gorshkov\CatalogSentinel\Domain\Rule\RuleContext;
use Gorshkov\CatalogSentinel\Domain\Rule\RuleInterface;
use Gorshkov\CatalogSentinel\Domain\Rule\RuleOutcome;
use Gorshkov\CatalogSentinel\Domain\Rule\RuleResult;

final class WellFormedRule implements RuleInterface
{
    public const CODE = 'xml.well_formed';

    public function code(): string
    {
        return self::CODE;
    }

    public function evaluate(RuleContext $context): RuleResult
    {
        if (!$context->analysis->wellFormed) {
            return new RuleResult(
                self::CODE,
                RuleOutcome::BLOCK,
                $context->analysis->errorCode === 'UNSAFE_XML' ? 'xml.unsafe' : 'xml.malformed',
                ['error_code' => $context->analysis->errorCode],
                evaluatedAt: $context->evaluatedAt,
            );
        }

        return new RuleResult(
            self::CODE,
            RuleOutcome::PASS,
            'xml.well_formed',
            evaluatedAt: $context->evaluatedAt,
        );
    }
}
