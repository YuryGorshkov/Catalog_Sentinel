<?php

declare(strict_types=1);

namespace Gorshkov\CatalogSentinel\Application\Baseline;

use Gorshkov\CatalogSentinel\Application\Contract\BaselineRepositoryInterface;

final class ResetBaseline
{
    public function __construct(private readonly BaselineRepositoryInterface $baselines)
    {
    }

    public function execute(string $sourceKey, string $documentKind, int $schemaVersion): int
    {
        return $this->baselines->reset($sourceKey, $documentKind, $schemaVersion);
    }
}
