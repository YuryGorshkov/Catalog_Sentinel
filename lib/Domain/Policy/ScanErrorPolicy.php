<?php

declare(strict_types=1);

namespace Gorshkov\CatalogSentinel\Domain\Policy;

final class ScanErrorPolicy
{
    public const ALLOW_AND_ALERT = 'ALLOW_AND_ALERT';
    public const BLOCK_AND_ALERT = 'BLOCK_AND_ALERT';

    private function __construct()
    {
    }
}
