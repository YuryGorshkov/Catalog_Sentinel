<?php

declare(strict_types=1);

namespace Gorshkov\CatalogSentinel\Domain\Rule;

final class RuleEvaluation
{
    /** @param list<RuleResult> $results */
    public function __construct(
        public readonly string $decision,
        public readonly array $results,
    ) {
    }
}
