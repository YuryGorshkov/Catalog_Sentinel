<?php

declare(strict_types=1);

namespace Gorshkov\CatalogSentinel\Domain\Analysis;

final class DocumentKind
{
    public const CATALOG = 'CATALOG';
    public const OFFERS = 'OFFERS';
    public const CLASSIFIER = 'CLASSIFIER';
    public const UNKNOWN_COMMERCE_ML = 'UNKNOWN_COMMERCE_ML';
    public const NOT_COMMERCE_ML = 'NOT_COMMERCE_ML';
    public const MALFORMED = 'MALFORMED';

    private function __construct()
    {
    }

    public static function isSupported(string $kind): bool
    {
        return $kind === self::CATALOG || $kind === self::OFFERS;
    }
}
