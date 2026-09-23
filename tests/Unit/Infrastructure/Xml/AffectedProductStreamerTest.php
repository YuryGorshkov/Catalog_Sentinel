<?php

declare(strict_types=1);

namespace Gorshkov\CatalogSentinel\Tests\Unit\Infrastructure\Xml;

use Gorshkov\CatalogSentinel\Infrastructure\Xml\AffectedProductStreamer;
use PHPUnit\Framework\TestCase;

final class AffectedProductStreamerTest extends TestCase
{
    public function testItStreamsEveryProductMatchingRequestedRules(): void
    {
        $path = dirname(__DIR__, 3) . '/Fixture/commerce_ml/normal_offers.xml';
        $rows = iterator_to_array((new AffectedProductStreamer())->rows(
            $path,
            ['stock.zero_spike', 'price.zero_spike'],
        ));

        self::assertSame(
            [
                ['rule_code' => 'stock.zero_spike', 'product_name' => 'Синие джинсы, размер 48', 'external_id' => 'offer-2', 'measured_value' => '0'],
                ['rule_code' => 'price.zero_spike', 'product_name' => 'Синие джинсы, размер 48', 'external_id' => 'offer-2', 'measured_value' => '0'],
                ['rule_code' => 'stock.zero_spike', 'product_name' => 'Красная куртка, размер L', 'external_id' => 'offer-3', 'measured_value' => '0'],
            ],
            $rows,
        );
    }

    public function testItRejectsDocumentTypeDeclarations(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'gcs_xml_');
        self::assertIsString($path);
        file_put_contents($path, '<!DOCTYPE x [<!ENTITY y SYSTEM "file:///etc/passwd">]><x>&y;</x>');

        try {
            $this->expectException(\RuntimeException::class);
            iterator_to_array((new AffectedProductStreamer())->rows($path, ['stock.zero_spike']));
        } finally {
            unlink($path);
        }
    }
}
