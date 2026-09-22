<?php

declare(strict_types=1);

use Gorshkov\CatalogSentinel\Tools\LargeFixtureGenerator;

require __DIR__ . '/LargeFixtureGenerator.php';

$options = getopt('', ['output:', 'count:', 'zero-stock-ratio::', 'zero-price-ratio::']);
$output = isset($options['output']) ? (string) $options['output'] : '';
$count = isset($options['count']) ? filter_var($options['count'], FILTER_VALIDATE_INT) : false;
$stockRatio = isset($options['zero-stock-ratio']) ? filter_var($options['zero-stock-ratio'], FILTER_VALIDATE_FLOAT) : 0.08;
$priceRatio = isset($options['zero-price-ratio']) ? filter_var($options['zero-price-ratio'], FILTER_VALIDATE_FLOAT) : 0.02;

if ($output === '' || $count === false || $stockRatio === false || $priceRatio === false) {
    fwrite(STDERR, 'Usage: php tools/generate-large-fixture.php --output=<file> --count=<n> [--zero-stock-ratio=0.08] [--zero-price-ratio=0.02]' . PHP_EOL);
    exit(2);
}

try {
    (new LargeFixtureGenerator())->generate($output, $count, $stockRatio, $priceRatio);
    fwrite(STDOUT, json_encode([
        'output' => realpath($output),
        'count' => $count,
        'zero_stock_ratio' => $stockRatio,
        'zero_price_ratio' => $priceRatio,
        'bytes' => filesize($output),
    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) . PHP_EOL);
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(2);
}
