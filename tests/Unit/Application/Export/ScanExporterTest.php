<?php

declare(strict_types=1);

namespace Gorshkov\CatalogSentinel\Tests\Unit\Application\Export;

use Gorshkov\CatalogSentinel\Application\DTO\FileIdentity;
use Gorshkov\CatalogSentinel\Application\DTO\ScanRecord;
use Gorshkov\CatalogSentinel\Application\Export\ScanExporter;
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
        );
        $exporter = new ScanExporter();

        self::assertStringNotContainsString('C:\\', $exporter->json($scan));
        self::assertStringContainsString("'=SUM(1+1)", $exporter->csv($scan));
    }
}
