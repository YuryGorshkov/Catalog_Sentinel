<?php

declare(strict_types=1);

namespace Gorshkov\CatalogSentinel\Application\Contract;

interface LoggerInterface
{
    /** @param array<string, scalar|null> $context */
    public function error(string $code, array $context = []): void;

    /** @param array<string, scalar|null> $context */
    public function warning(string $code, array $context = []): void;
}
