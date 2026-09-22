<?php

declare(strict_types=1);

use Gorshkov\CatalogSentinel\Tools\ReleaseArchive;

require __DIR__ . '/ReleaseArchive.php';

$root = dirname(__DIR__);
$output = $argv[1] ?? $root . '/build/gorshkov.catalogsentinel-1.0.0.zip';

try {
    $result = (new ReleaseArchive())->build($root, $output);
    fwrite(STDOUT, json_encode($result, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
}
