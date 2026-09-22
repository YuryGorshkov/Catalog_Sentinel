<?php

declare(strict_types=1);

namespace Gorshkov\CatalogSentinel\Application\DTO;

final class ScanOutcome
{
    public function __construct(
        public readonly bool $blocked,
        public readonly string $message,
        public readonly ?ScanRecord $scan,
        public readonly bool $fromCache = false,
    ) {
    }
}
