<?php

declare(strict_types=1);

namespace Gorshkov\CatalogSentinel\Infrastructure\Xml;

final class CurrentPropertyState
{
    public ?string $externalId = null;
    public bool $valueSeen = false;
    public bool $hasNonEmptyValue = false;

    public function addValue(string $value): void
    {
        $this->valueSeen = true;
        if (trim($value) !== '') {
            $this->hasNonEmptyValue = true;
        }
    }
}
