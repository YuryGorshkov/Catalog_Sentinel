<?php

declare(strict_types=1);

namespace Gorshkov\CatalogSentinel\Domain\Rule;

final class Decision
{
    public const PASS = 'PASS';
    public const WARN = 'WARN';
    public const BLOCK = 'BLOCK';
    public const ERROR = 'ERROR';

    private function __construct()
    {
    }
}
