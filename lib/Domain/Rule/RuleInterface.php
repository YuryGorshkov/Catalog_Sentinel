<?php

declare(strict_types=1);

namespace Gorshkov\CatalogSentinel\Domain\Rule;

interface RuleInterface
{
    public function code(): string;

    public function evaluate(RuleContext $context): RuleResult;
}
