<?php

declare(strict_types=1);

namespace Gorshkov\CatalogSentinel\Tests\Unit\Domain\Baseline;

use Gorshkov\CatalogSentinel\Domain\Baseline\BaselineSnapshot;
use Gorshkov\CatalogSentinel\Domain\Baseline\MedianCalculator;
use PHPUnit\Framework\TestCase;

final class MedianCalculatorTest extends TestCase
{
    public function testOddAndEvenMedians(): void
    {
        $calculator = new MedianCalculator();

        self::assertSame(5.0, $calculator->calculate([9, 1, 5]));
        self::assertSame(5.0, $calculator->calculate([9, 1, 7, 3]));
    }

    public function testBaselineUsesOnlyLatestWindow(): void
    {
        $baseline = BaselineSnapshot::fromSamples(
            'source',
            'OFFERS',
            1,
            3,
            3,
            [
                ['document.objects_total' => 1],
                ['document.objects_total' => 100],
                ['document.objects_total' => 110],
                ['document.objects_total' => 120],
            ],
        );

        self::assertSame(110.0, $baseline->median('document.objects_total'));
        self::assertSame(3, $baseline->sampleCount);
        self::assertTrue($baseline->isReadyFor(1));
        self::assertFalse($baseline->isReadyFor(2));
    }
}
