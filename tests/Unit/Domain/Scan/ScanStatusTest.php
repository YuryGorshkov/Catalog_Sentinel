<?php

declare(strict_types=1);

namespace Gorshkov\CatalogSentinel\Tests\Unit\Domain\Scan;

use DomainException;
use Gorshkov\CatalogSentinel\Domain\Scan\ScanStatus;
use PHPUnit\Framework\TestCase;

final class ScanStatusTest extends TestCase
{
    public function testAllowedTransition(): void
    {
        ScanStatus::assertTransition(ScanStatus::SCANNING, ScanStatus::OBSERVED);
        $this->addToAssertionCount(1);
    }

    public function testInvalidTransitionIsRejected(): void
    {
        $this->expectException(DomainException::class);
        ScanStatus::assertTransition(ScanStatus::BLOCKED, ScanStatus::IMPORTED);
    }
}
