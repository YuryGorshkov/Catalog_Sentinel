<?php

declare(strict_types=1);

namespace Gorshkov\CatalogSentinel\Domain\Rule;

use Gorshkov\CatalogSentinel\Domain\Analysis\AnalysisResult;
use Gorshkov\CatalogSentinel\Domain\Baseline\BaselineSnapshot;
use Gorshkov\CatalogSentinel\Domain\Policy\PolicySnapshot;

final class RuleContext
{
    public function __construct(
        public readonly AnalysisResult $analysis,
        public readonly PolicySnapshot $policy,
        public readonly BaselineSnapshot $baseline,
        public readonly string $evaluatedAt,
    ) {
    }
}
