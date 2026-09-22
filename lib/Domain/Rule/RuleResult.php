<?php

declare(strict_types=1);

namespace Gorshkov\CatalogSentinel\Domain\Rule;

final class RuleResult
{
    /**
     * @param array<string, scalar|null> $actual
     * @param array<string, scalar|null> $thresholds
     * @param array<string, scalar|null> $baseline
     * @param list<string> $sampleIds
     * @param array<string, scalar|null> $messageParams
     */
    public function __construct(
        public readonly string $ruleCode,
        public readonly string $outcome,
        public readonly string $reasonCode,
        public readonly array $actual = [],
        public readonly array $thresholds = [],
        public readonly array $baseline = [],
        public readonly array $sampleIds = [],
        public readonly array $messageParams = [],
        public readonly string $evaluatedAt = '',
    ) {
    }
}
