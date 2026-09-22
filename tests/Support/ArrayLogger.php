<?php

declare(strict_types=1);

namespace Gorshkov\CatalogSentinel\Tests\Support;

use Gorshkov\CatalogSentinel\Application\Contract\LoggerInterface;

final class ArrayLogger implements LoggerInterface
{
    /** @var list<string> */
    public array $errors = [];
    /** @var list<string> */
    public array $warnings = [];

    public function error(string $code, array $context = []): void
    {
        $this->errors[] = $code;
    }

    public function warning(string $code, array $context = []): void
    {
        $this->warnings[] = $code;
    }
}
