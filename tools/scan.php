<?php

declare(strict_types=1);

use Gorshkov\CatalogSentinel\Application\Export\ResultNormalizer;
use Gorshkov\CatalogSentinel\Domain\Baseline\BaselineSnapshot;
use Gorshkov\CatalogSentinel\Domain\Policy\Mode;
use Gorshkov\CatalogSentinel\Domain\Policy\PolicySnapshot;
use Gorshkov\CatalogSentinel\Domain\Rule\Decision;
use Gorshkov\CatalogSentinel\Domain\Rule\DefaultRuleSet;
use Gorshkov\CatalogSentinel\Domain\Rule\RuleContext;
use Gorshkov\CatalogSentinel\Domain\Rule\RuleEngine;
use Gorshkov\CatalogSentinel\Infrastructure\Support\SafeSourceKeyResolver;
use Gorshkov\CatalogSentinel\Infrastructure\Xml\XmlReaderCommerceMlAnalyzer;

require __DIR__ . '/bootstrap.php';

$arguments = array_slice($argv, 1);
$path = null;
$mode = Mode::OBSERVE;
$baselinePath = null;
foreach ($arguments as $argument) {
    if (str_starts_with($argument, '--mode=')) {
        $mode = strtoupper(substr($argument, 7));
    } elseif (str_starts_with($argument, '--baseline=')) {
        $baselinePath = substr($argument, 11);
    } elseif (!str_starts_with($argument, '--') && $path === null) {
        $path = $argument;
    } else {
        fwrite(STDERR, 'Unknown argument: ' . $argument . PHP_EOL);
        exit(2);
    }
}

if ($path === null || !in_array($mode, [Mode::OBSERVE, Mode::PROTECT], true)) {
    fwrite(STDERR, 'Usage: php tools/scan.php <file.xml> [--mode=OBSERVE|PROTECT] [--baseline=baseline.json]' . PHP_EOL);
    exit(2);
}

try {
    $policy = PolicySnapshot::defaults($mode);
    $analysis = (new XmlReaderCommerceMlAnalyzer())->analyze($path, $policy);
    $source = (new SafeSourceKeyResolver())->resolve([], $analysis);
    $baseline = BaselineSnapshot::empty($source->key, $analysis->documentKind, $policy->analyzerSchemaVersion, $policy->baselineMinSamples);
    if ($baselinePath !== null) {
        $json = file_get_contents($baselinePath);
        if ($json === false) {
            throw new RuntimeException('Cannot read baseline file.');
        }
        $data = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
        if (!is_array($data) || !isset($data['samples']) || !is_array($data['samples'])) {
            throw new RuntimeException('Baseline JSON must contain a samples array.');
        }
        $baseline = BaselineSnapshot::fromSamples(
            $source->key,
            $analysis->documentKind,
            $policy->analyzerSchemaVersion,
            $policy->baselineMinSamples,
            $policy->baselineWindow,
            $data['samples'],
        );
    }
    $evaluation = (new RuleEngine(DefaultRuleSet::create()))->evaluate(
        new RuleContext($analysis, $policy, $baseline, (new DateTimeImmutable())->format(DATE_ATOM)),
    );
    $normalizer = new ResultNormalizer();
    $output = [
        'schema' => 'gorshkov.catalogsentinel.cli.v1',
        'mode' => $mode,
        'action' => $mode === Mode::PROTECT && $evaluation->decision === Decision::BLOCK ? 'BLOCK' : 'ALLOW',
        'analysis' => $normalizer->analysis($analysis),
        'evaluation' => $normalizer->evaluation($evaluation),
    ];
    fwrite(STDOUT, json_encode($output, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL);
    exit($evaluation->decision === Decision::BLOCK ? 3 : 0);
} catch (Throwable $exception) {
    fwrite(STDERR, json_encode([
        'schema' => 'gorshkov.catalogsentinel.cli.v1',
        'error' => 'CLI_ERROR',
        'message' => $exception->getMessage(),
    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) . PHP_EOL);
    exit(2);
}
