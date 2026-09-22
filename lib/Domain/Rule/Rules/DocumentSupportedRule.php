<?php

declare(strict_types=1);

namespace Gorshkov\CatalogSentinel\Domain\Rule\Rules;

use Gorshkov\CatalogSentinel\Domain\Analysis\DocumentKind;
use Gorshkov\CatalogSentinel\Domain\Rule\RuleContext;
use Gorshkov\CatalogSentinel\Domain\Rule\RuleInterface;
use Gorshkov\CatalogSentinel\Domain\Rule\RuleOutcome;
use Gorshkov\CatalogSentinel\Domain\Rule\RuleResult;

final class DocumentSupportedRule implements RuleInterface
{
    public const CODE = 'document.supported';

    public function code(): string
    {
        return self::CODE;
    }

    public function evaluate(RuleContext $context): RuleResult
    {
        $kind = $context->analysis->documentKind;
        if ($kind === DocumentKind::UNKNOWN_COMMERCE_ML) {
            return new RuleResult(
                self::CODE,
                RuleOutcome::WARN,
                'document.unknown_commerce_ml',
                ['document_kind' => $kind],
                evaluatedAt: $context->evaluatedAt,
            );
        }
        if ($kind === DocumentKind::NOT_COMMERCE_ML || $kind === DocumentKind::CLASSIFIER) {
            return new RuleResult(
                self::CODE,
                RuleOutcome::NOT_APPLICABLE,
                'document.not_importable',
                ['document_kind' => $kind],
                evaluatedAt: $context->evaluatedAt,
            );
        }

        return new RuleResult(
            self::CODE,
            RuleOutcome::PASS,
            'document.supported',
            ['document_kind' => $kind],
            evaluatedAt: $context->evaluatedAt,
        );
    }
}
