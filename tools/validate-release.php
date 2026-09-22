<?php

declare(strict_types=1);

use Gorshkov\CatalogSentinel\Tools\ReleaseArchive;

require __DIR__ . '/ReleaseArchive.php';

$archive = $argv[1] ?? dirname(__DIR__) . '/build/gorshkov.catalogsentinel-1.0.0.zip';
try {
    $result = (new ReleaseArchive())->validate($archive);
    fwrite(STDOUT, json_encode($result, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT) . PHP_EOL);
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
}
