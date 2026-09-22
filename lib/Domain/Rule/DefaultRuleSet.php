<?php

declare(strict_types=1);

namespace Gorshkov\CatalogSentinel\Domain\Rule;

use Gorshkov\CatalogSentinel\Domain\Rule\Rules\DocumentSupportedRule;
use Gorshkov\CatalogSentinel\Domain\Rule\Rules\FileSizeDropRule;
use Gorshkov\CatalogSentinel\Domain\Rule\Rules\IdentifierMissingRatioRule;
use Gorshkov\CatalogSentinel\Domain\Rule\Rules\ItemCountDropRule;
use Gorshkov\CatalogSentinel\Domain\Rule\Rules\PriceZeroSpikeRule;
use Gorshkov\CatalogSentinel\Domain\Rule\Rules\PropertyEmptySpikeRule;
use Gorshkov\CatalogSentinel\Domain\Rule\Rules\StockZeroSpikeRule;
use Gorshkov\CatalogSentinel\Domain\Rule\Rules\WellFormedRule;

final class DefaultRuleSet
{
    private function __construct()
    {
    }

    /** @return list<RuleInterface> */
    public static function create(): array
    {
        return [
            new WellFormedRule(),
            new DocumentSupportedRule(),
            new ItemCountDropRule(),
            new StockZeroSpikeRule(),
            new PriceZeroSpikeRule(),
            new PropertyEmptySpikeRule(),
            new IdentifierMissingRatioRule(),
            new FileSizeDropRule(),
        ];
    }
}
