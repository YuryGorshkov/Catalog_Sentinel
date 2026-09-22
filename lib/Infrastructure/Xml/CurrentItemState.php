<?php

declare(strict_types=1);

namespace Gorshkov\CatalogSentinel\Infrastructure\Xml;

final class CurrentItemState
{
    public ?string $externalId = null;

    /** @var list<float> */
    public array $generalStockValues = [];

    /** @var list<float> */
    public array $warehouseStockValues = [];

    /** @var list<float> */
    public array $priceValues = [];

    /** @var array<string, bool> external ID => has non-empty value */
    public array $properties = [];

    public int $numericErrors = 0;

    public function __construct(
        public readonly string $kind,
        public readonly int $ordinal,
        public readonly int $depth,
    ) {
    }

    public function sampleId(): string
    {
        return $this->externalId ?? '#' . $this->ordinal;
    }
}
