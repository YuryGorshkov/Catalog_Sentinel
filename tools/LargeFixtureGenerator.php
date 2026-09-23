<?php

declare(strict_types=1);

namespace Gorshkov\CatalogSentinel\Tools;

use InvalidArgumentException;
use RuntimeException;

final class LargeFixtureGenerator
{
    public function generate(string $output, int $count, float $zeroStockRatio, float $zeroPriceRatio): void
    {
        if ($count < 1 || $zeroStockRatio < 0 || $zeroStockRatio > 1 || $zeroPriceRatio < 0 || $zeroPriceRatio > 1) {
            throw new InvalidArgumentException('Invalid fixture generator parameters.');
        }
        $directory = dirname($output);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('Cannot create output directory.');
        }
        $stream = fopen($output, 'wb');
        if ($stream === false) {
            throw new RuntimeException('Cannot open output file.');
        }
        $zeroStocks = (int) floor($count * $zeroStockRatio);
        $zeroPrices = (int) floor($count * $zeroPriceRatio);
        try {
            fwrite($stream, '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL);
            fwrite($stream, '<КоммерческаяИнформация ВерсияСхемы="2.10"><ПакетПредложений><Ид>generated</Ид><Предложения>' . PHP_EOL);
            for ($i = 0; $i < $count; ++$i) {
                $stock = $i < $zeroStocks ? '0' : '10';
                $price = $i < $zeroPrices ? '0' : '100';
                fwrite(
                    $stream,
                    '<Предложение><Ид>generated-' . $i . '</Ид><Наименование>Тестовый товар ' . ($i + 1)
                    . '</Наименование><Количество>' . $stock
                    . '</Количество><Цены><Цена><ЦенаЗаЕдиницу>' . $price
                    . '</ЦенаЗаЕдиницу></Цена></Цены></Предложение>' . PHP_EOL,
                );
            }
            fwrite($stream, '</Предложения></ПакетПредложений></КоммерческаяИнформация>' . PHP_EOL);
        } finally {
            fclose($stream);
        }
    }
}
