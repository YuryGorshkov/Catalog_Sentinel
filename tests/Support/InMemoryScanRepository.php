<?php

declare(strict_types=1);

namespace Gorshkov\CatalogSentinel\Tests\Support;

use DateTimeImmutable;
use Gorshkov\CatalogSentinel\Application\Contract\ScanRepositoryInterface;
use Gorshkov\CatalogSentinel\Application\DTO\ScanRecord;
use Gorshkov\CatalogSentinel\Domain\Scan\ScanStatus;

class InMemoryScanRepository implements ScanRepositoryInterface
{
    /** @var array<int, ScanRecord> */
    private array $records = [];
    private int $nextId = 1;

    public function save(ScanRecord $scan): ScanRecord
    {
        $stored = $scan->id === null ? $scan->withId($this->nextId++) : $scan;
        $this->records[$stored->id ?? 0] = $stored;

        return $stored;
    }

    public function findCached(string $identityKey, int $policyVersion, DateTimeImmutable $now): ?ScanRecord
    {
        foreach (array_reverse($this->records, true) as $record) {
            if (
                $record->file->identityKey === $identityKey
                && $record->policyVersion === $policyVersion
                && new DateTimeImmutable($record->cacheUntil) >= $now
            ) {
                return $record;
            }
        }

        return null;
    }

    public function findImportCandidates(string $identityKey, DateTimeImmutable $since): array
    {
        return array_values(array_filter(
            $this->records,
            static fn (ScanRecord $record): bool => $record->file->identityKey === $identityKey
                && in_array($record->status, [ScanStatus::OBSERVED, ScanStatus::ALLOWED], true)
                && new DateTimeImmutable($record->createdAt) >= $since,
        ));
    }

    public function markImported(int $scanId, DateTimeImmutable $at): bool
    {
        $record = $this->records[$scanId] ?? null;
        if ($record === null || !in_array($record->status, [ScanStatus::OBSERVED, ScanStatus::ALLOWED], true)) {
            return false;
        }
        $this->records[$scanId] = $record->imported($at->format(DATE_ATOM));

        return true;
    }

    public function findById(int $scanId): ?ScanRecord
    {
        return $this->records[$scanId] ?? null;
    }

    public function deleteOlderThan(DateTimeImmutable $before, int $limit): int
    {
        $deleted = 0;
        foreach ($this->records as $id => $record) {
            if ($deleted >= $limit) {
                break;
            }
            if (new DateTimeImmutable($record->createdAt) < $before) {
                unset($this->records[$id]);
                ++$deleted;
            }
        }

        return $deleted;
    }

    /** @return list<ScanRecord> */
    public function all(): array
    {
        return array_values($this->records);
    }
}
