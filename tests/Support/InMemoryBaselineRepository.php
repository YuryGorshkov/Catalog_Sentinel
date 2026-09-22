<?php

declare(strict_types=1);

namespace Gorshkov\CatalogSentinel\Tests\Support;

use Gorshkov\CatalogSentinel\Application\Contract\BaselineRepositoryInterface;
use Gorshkov\CatalogSentinel\Application\Baseline\BaselineMetricFilter;
use Gorshkov\CatalogSentinel\Application\DTO\ScanRecord;
use Gorshkov\CatalogSentinel\Domain\Baseline\BaselineSnapshot;
use Gorshkov\CatalogSentinel\Domain\Policy\PolicySnapshot;

final class InMemoryBaselineRepository implements BaselineRepositoryInterface
{
    /** @var array<string, array{scan: ScanRecord, origin: string, accepted_by: int|null}> */
    private array $samples = [];

    public function get(string $sourceKey, string $documentKind, PolicySnapshot $policy): BaselineSnapshot
    {
        $matching = [];
        foreach ($this->samples as $sample) {
            $scan = $sample['scan'];
            if (
                $scan->sourceKey === $sourceKey
                && $scan->documentKind === $documentKind
                && $scan->analyzerSchemaVersion === $policy->analyzerSchemaVersion
            ) {
                $matching[] = $scan->metrics;
            }
        }

        return BaselineSnapshot::fromSamples(
            $sourceKey,
            $documentKind,
            $policy->analyzerSchemaVersion,
            $policy->baselineMinSamples,
            $policy->baselineWindow,
            $matching,
        );
    }

    public function addSample(ScanRecord $scan, string $origin, ?int $acceptedBy = null): bool
    {
        if ($scan->id === null) {
            return false;
        }
        $key = $scan->id . '|' . $origin;
        if (isset($this->samples[$key])) {
            return false;
        }
        $filtered = new ScanRecord(
            $scan->id,
            $scan->createdAt,
            $scan->updatedAt,
            $scan->finishedAt,
            $scan->importedAt,
            $scan->mode,
            $scan->status,
            $scan->decision,
            $scan->sourceKey,
            $scan->sourceLabel,
            $scan->documentKind,
            $scan->file,
            $scan->policyVersion,
            $scan->analyzerSchemaVersion,
            (new BaselineMetricFilter())->filter($scan->metrics),
            $scan->samples,
            $scan->warnings,
            $scan->errorCode,
            $scan->errorMessage,
            $scan->durationMs,
            $scan->memoryDeltaBytes,
            $scan->dryRun,
            $scan->cacheUntil,
            $scan->ruleResults,
        );
        $this->samples[$key] = ['scan' => $filtered, 'origin' => $origin, 'accepted_by' => $acceptedBy];

        return true;
    }

    public function reset(string $sourceKey, string $documentKind, int $schemaVersion): int
    {
        $deleted = 0;
        foreach ($this->samples as $key => $sample) {
            $scan = $sample['scan'];
            if ($scan->sourceKey === $sourceKey && $scan->documentKind === $documentKind && $scan->analyzerSchemaVersion === $schemaVersion) {
                unset($this->samples[$key]);
                ++$deleted;
            }
        }

        return $deleted;
    }

    public function cleanup(int $retentionDays, int $window): int
    {
        return 0;
    }

    public function count(): int
    {
        return count($this->samples);
    }
}
