<?php

declare(strict_types=1);

use Gorshkov\CatalogSentinel\Domain\Analysis\MetricNames;
use Gorshkov\CatalogSentinel\Tools\LargeFixtureGenerator;

require dirname(__DIR__, 2) . '/vendor/autoload.php';
require dirname(__DIR__, 2) . '/tools/LargeFixtureGenerator.php';

$root = dirname(__DIR__, 2);
$counts = [10_000, 250_000];
$results = [];
$generator = new LargeFixtureGenerator();

foreach ($counts as $count) {
    $path = tempnam(sys_get_temp_dir(), 'gcs_perf_');
    if ($path === false) {
        throw new RuntimeException('Cannot create performance fixture.');
    }
    try {
        $generator->generate($path, $count, 0.08, 0.02);
        $command = [PHP_BINARY, '-d', 'memory_limit=256M', $root . '/tools/scan.php', $path];
        $descriptor = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = proc_open($command, $descriptor, $pipes, $root);
        if (!is_resource($process)) {
            throw new RuntimeException('Cannot launch analyzer process.');
        }
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);
        if ($exit !== 0 || $stdout === false) {
            throw new RuntimeException('Analyzer failed: ' . $stderr);
        }
        $data = json_decode($stdout, true, 128, JSON_THROW_ON_ERROR);
        $metrics = $data['analysis']['metrics'];
        if ($metrics[MetricNames::OBJECTS_TOTAL] !== $count) {
            throw new RuntimeException('Object count mismatch.');
        }
        if (abs($metrics[MetricNames::STOCK_ZERO_RATIO] - 0.08) > 0.000001) {
            throw new RuntimeException('Stock ratio mismatch.');
        }
        $memory = $data['analysis']['peak_memory_delta_bytes'];
        if ($memory > 128 * 1024 * 1024) {
            throw new RuntimeException('Peak memory delta exceeds 128 MiB.');
        }
        $results[] = [
            'objects' => $count,
            'bytes' => filesize($path),
            'duration_ms' => $data['analysis']['duration_ms'],
            'peak_memory_bytes' => $data['analysis']['document_metadata']['peak_memory_bytes'],
            'peak_memory_delta_bytes' => $memory,
        ];
    } finally {
        @unlink($path);
    }
}

if ($results[1]['peak_memory_delta_bytes'] - $results[0]['peak_memory_delta_bytes'] > 32 * 1024 * 1024) {
    throw new RuntimeException('Peak memory growth exceeds 32 MiB.');
}

fwrite(STDOUT, json_encode([
    'php' => PHP_VERSION,
    'memory_limit' => '256M',
    'results' => $results,
], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT) . PHP_EOL);
