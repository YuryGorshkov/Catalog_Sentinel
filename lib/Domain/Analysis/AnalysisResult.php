<?php

declare(strict_types=1);

namespace Gorshkov\CatalogSentinel\Domain\Analysis;

final class AnalysisResult
{
    /**
     * @param array<string, list<string>> $samples
     * @param array<string, int> $valueErrors
     * @param list<string> $parserWarnings
     * @param array<string, string> $sourceHints
     * @param array<string, scalar|null> $documentMetadata
     */
    public function __construct(
        public readonly string $documentKind,
        public readonly bool $supported,
        public readonly bool $wellFormed,
        public readonly AnalysisMetrics $metrics,
        public readonly array $samples = [],
        public readonly array $valueErrors = [],
        public readonly array $parserWarnings = [],
        public readonly array $sourceHints = [],
        public readonly int $durationMs = 0,
        public readonly int $peakMemoryDeltaBytes = 0,
        public readonly array $documentMetadata = [],
        public readonly ?string $errorCode = null,
    ) {
    }
}
