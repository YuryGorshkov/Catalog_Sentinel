<?php

declare(strict_types=1);

namespace Gorshkov\CatalogSentinel\Application\Contract;

use Gorshkov\CatalogSentinel\Application\DTO\ScanRecord;
use Gorshkov\CatalogSentinel\Domain\Baseline\BaselineSnapshot;
use Gorshkov\CatalogSentinel\Domain\Policy\PolicySnapshot;

interface BaselineRepositoryInterface
{
    public function get(string $sourceKey, string $documentKind, PolicySnapshot $policy): BaselineSnapshot;

    public function addSample(ScanRecord $scan, string $origin, ?int $acceptedBy = null): bool;

    public function reset(string $sourceKey, string $documentKind, int $schemaVersion): int;

    public function cleanup(int $retentionDays, int $window): int;
}
