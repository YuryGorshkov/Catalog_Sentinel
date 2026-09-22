<?php

declare(strict_types=1);

namespace Gorshkov\CatalogSentinel\Application\Contract;

use Gorshkov\CatalogSentinel\Application\DTO\SourceIdentity;
use Gorshkov\CatalogSentinel\Domain\Analysis\AnalysisResult;

interface SourceKeyResolverInterface
{
    /** @param array<string, mixed> $parameters */
    public function resolve(array $parameters, AnalysisResult $analysis, ?string $label = null): SourceIdentity;
}
