<?php

declare(strict_types=1);

namespace Gorshkov\CatalogSentinel\Application\Scan;

use DateInterval;
use Gorshkov\CatalogSentinel\Application\Contract\BaselineRepositoryInterface;
use Gorshkov\CatalogSentinel\Application\Contract\ClockInterface;
use Gorshkov\CatalogSentinel\Application\Contract\FileIdentityFactoryInterface;
use Gorshkov\CatalogSentinel\Application\Contract\LoggerInterface;
use Gorshkov\CatalogSentinel\Application\Contract\ScanRepositoryInterface;

final class MarkImportSuccessful
{
    public function __construct(
        private readonly ScanRepositoryInterface $scans,
        private readonly BaselineRepositoryInterface $baselines,
        private readonly FileIdentityFactoryInterface $identities,
        private readonly ClockInterface $clock,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function execute(string $path): bool
    {
        $now = $this->clock->now();
        try {
            $identity = $this->identities->create($path);
            $candidates = $this->scans->findImportCandidates(
                $identity->identityKey,
                $now->sub(new DateInterval('PT30M')),
            );
            if (count($candidates) !== 1 || $candidates[0]->id === null) {
                $this->logger->warning('success.correlation_ambiguous', ['candidate_count' => count($candidates)]);

                return false;
            }
            $scan = $candidates[0];
            if (!$this->scans->markImported($scan->id, $now)) {
                return false;
            }

            return $this->baselines->addSample($scan->imported($now->format(DATE_ATOM)), 'IMPORT');
        } catch (\Throwable $exception) {
            $this->logger->error('success.internal_error', ['exception' => $exception::class]);

            return false;
        }
    }
}
