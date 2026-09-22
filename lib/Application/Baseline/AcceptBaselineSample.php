<?php

declare(strict_types=1);

namespace Gorshkov\CatalogSentinel\Application\Baseline;

use Gorshkov\CatalogSentinel\Application\Contract\BaselineRepositoryInterface;
use Gorshkov\CatalogSentinel\Application\Contract\ScanRepositoryInterface;
use Gorshkov\CatalogSentinel\Domain\Rule\Decision;
use Gorshkov\CatalogSentinel\Domain\Scan\ScanStatus;

final class AcceptBaselineSample
{
    public function __construct(
        private readonly ScanRepositoryInterface $scans,
        private readonly BaselineRepositoryInterface $baselines,
    ) {
    }

    public function execute(int $scanId, int $userId): bool
    {
        $scan = $this->scans->findById($scanId);
        if ($scan === null || $scan->status !== ScanStatus::DRY_RUN || $scan->decision === Decision::BLOCK) {
            return false;
        }

        return $this->baselines->addSample($scan, 'MANUAL', $userId);
    }
}
