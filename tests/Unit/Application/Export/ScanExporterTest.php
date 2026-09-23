<?php

declare(strict_types=1);

namespace Gorshkov\CatalogSentinel\Tests\Unit\Application\Export;

use Gorshkov\CatalogSentinel\Application\DTO\FileIdentity;
use Gorshkov\CatalogSentinel\Application\DTO\ScanRecord;
use Gorshkov\CatalogSentinel\Application\Export\ScanExporter;
use Gorshkov\CatalogSentinel\Domain\Rule\RuleResult;
use PHPUnit\Framework\TestCase;

final class ScanExporterTest extends TestCase
{
    public function testExportOmitsAbsolutePathAndNeutralizesCsvFormula(): void
    {
        $scan = new ScanRecord(
            42,
            '2026-09-22T10:00:00+03:00',
            '2026-09-22T10:00:00+03:00',
            '2026-09-22T10:00:00+03:00',
            null,
            'OBSERVE',
            'OBSERVED',
            'WARN',
            str_repeat('a', 64),
            '=SUM(1+1)',
            'OFFERS',
            new FileIdentity('safe.xml', 10, 1, str_repeat('b', 64), null, str_repeat('c', 64)),
            1,
            1,
            ['document.objects_total' => 1],
            [],
            [],
            null,
            null,
            1,
            0,
            false,
            '2026-09-22T10:30:00+03:00',
            [
                new RuleResult(
                    'stock.zero_spike',
                    'BLOCK',
                    'stock_zero_spike',
                    ['ratio' => 0.92],
                    ['min_current_ratio' => 0.8],
                    ['median_ratio' => 0.08],
                ),
            ],
        );
        $exporter = new ScanExporter();

        $json = $exporter->json($scan);
        self::assertStringNotContainsString('C:\\', $json);
        self::assertStringContainsString('stock.zero_spike', $json);
        self::assertStringContainsString('min_current_ratio', $json);
        self::assertStringContainsString('median_ratio', $json);
        self::assertStringContainsString("'=SUM(1+1)", $exporter->csv($scan));
    }
}
