<?php

declare(strict_types=1);

namespace Gorshkov\CatalogSentinel\Application\DTO;

final class SourceIdentity
{
    public function __construct(
        public readonly string $key,
        public readonly string $label,
    ) {
    }
}
