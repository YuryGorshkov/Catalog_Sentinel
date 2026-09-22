<?php

declare(strict_types=1);

namespace Gorshkov\CatalogSentinel\Domain\Policy;

final class Mode
{
    public const DISABLED = 'DISABLED';
    public const OBSERVE = 'OBSERVE';
    public const PROTECT = 'PROTECT';

    private function __construct()
    {
    }

    public static function isValid(string $mode): bool
    {
        return in_array($mode, [self::DISABLED, self::OBSERVE, self::PROTECT], true);
    }
}
