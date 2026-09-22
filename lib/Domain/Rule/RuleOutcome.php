<?php

declare(strict_types=1);

namespace Gorshkov\CatalogSentinel\Domain\Rule;

final class RuleOutcome
{
    public const PASS = 'PASS';
    public const WARN = 'WARN';
    public const BLOCK = 'BLOCK';
    public const NOT_APPLICABLE = 'NOT_APPLICABLE';
    public const ERROR = 'ERROR';

    private function __construct()
    {
    }
}
