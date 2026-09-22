<?php

declare(strict_types=1);

namespace Gorshkov\CatalogSentinel\Domain\Scan;

use DomainException;

final class ScanStatus
{
    public const SCANNING = 'SCANNING';
    public const OBSERVED = 'OBSERVED';
    public const ALLOWED = 'ALLOWED';
    public const BLOCKED = 'BLOCKED';
    public const IMPORTED = 'IMPORTED';
    public const UNSUPPORTED = 'UNSUPPORTED';
    public const SCAN_ERROR_ALLOWED = 'SCAN_ERROR_ALLOWED';
    public const SCAN_ERROR_BLOCKED = 'SCAN_ERROR_BLOCKED';
    public const STALE = 'STALE';
    public const DRY_RUN = 'DRY_RUN';

    /** @var array<string, list<string>> */
    private const TRANSITIONS = [
        self::SCANNING => [
            self::OBSERVED,
            self::ALLOWED,
            self::BLOCKED,
            self::UNSUPPORTED,
            self::SCAN_ERROR_ALLOWED,
            self::SCAN_ERROR_BLOCKED,
            self::DRY_RUN,
            self::STALE,
        ],
        self::OBSERVED => [self::IMPORTED, self::STALE],
        self::ALLOWED => [self::IMPORTED, self::STALE],
    ];

    private function __construct()
    {
    }

    public static function assertTransition(string $from, string $to): void
    {
        if (!in_array($to, self::TRANSITIONS[$from] ?? [], true)) {
            throw new DomainException(sprintf('Invalid scan status transition: %s -> %s.', $from, $to));
        }
    }
}
