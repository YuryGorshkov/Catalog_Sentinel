<?php

declare(strict_types=1);

namespace Gorshkov\CatalogSentinel\Application\Contract;

use DateTimeImmutable;
use Gorshkov\CatalogSentinel\Application\DTO\ScanRecord;

interface ScanRepositoryInterface
{
    public function save(ScanRecord $scan): ScanRecord;

    public function findCached(string $identityKey, int $policyVersion, DateTimeImmutable $now): ?ScanRecord;

    /** @return list<ScanRecord> */
    public function findImportCandidates(string $identityKey, DateTimeImmutable $since): array;

    public function markImported(int $scanId, DateTimeImmutable $at): bool;

    public function findById(int $scanId): ?ScanRecord;

    public function deleteOlderThan(DateTimeImmutable $before, int $limit): int;
}
