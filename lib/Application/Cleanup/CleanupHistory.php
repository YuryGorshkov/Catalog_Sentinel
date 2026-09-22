<?php

declare(strict_types=1);

namespace Gorshkov\CatalogSentinel\Application\Cleanup;

use DateInterval;
use Gorshkov\CatalogSentinel\Application\Contract\BaselineRepositoryInterface;
use Gorshkov\CatalogSentinel\Application\Contract\ClockInterface;
use Gorshkov\CatalogSentinel\Application\Contract\ScanRepositoryInterface;
use Gorshkov\CatalogSentinel\Application\Contract\SettingsProviderInterface;

final class CleanupHistory
{
    public function __construct(
        private readonly ScanRepositoryInterface $scans,
        private readonly BaselineRepositoryInterface $baselines,
        private readonly SettingsProviderInterface $settings,
        private readonly ClockInterface $clock,
    ) {
    }

    /** @return array{scans: int, samples: int} */
    public function execute(int $batchSize = 500): array
    {
        $policy = $this->settings->current();
        $before = $this->clock->now()->sub(new DateInterval('P' . $policy->retentionDays . 'D'));

        return [
            'scans' => $this->scans->deleteOlderThan($before, $batchSize),
            'samples' => $this->baselines->cleanup($policy->retentionDays, $policy->baselineWindow),
        ];
    }
}
