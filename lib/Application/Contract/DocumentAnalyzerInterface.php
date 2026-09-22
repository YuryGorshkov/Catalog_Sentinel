<?php

declare(strict_types=1);

namespace Gorshkov\CatalogSentinel\Application\Contract;

use Gorshkov\CatalogSentinel\Domain\Analysis\AnalysisResult;
use Gorshkov\CatalogSentinel\Domain\Policy\PolicySnapshot;

interface DocumentAnalyzerInterface
{
    public function analyze(string $path, PolicySnapshot $policy): AnalysisResult;
}
