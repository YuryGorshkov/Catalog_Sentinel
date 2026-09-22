<?php

declare(strict_types=1);

namespace Gorshkov\CatalogSentinel\Application\DTO;

use Gorshkov\CatalogSentinel\Domain\Rule\RuleResult;

final class ScanRecord
{
    /**
     * @param array<string, int|float> $metrics
     * @param array<string, list<string>> $samples
     * @param list<string> $warnings
     * @param list<RuleResult> $ruleResults
     */
    public function __construct(
        public readonly ?int $id,
        public readonly string $createdAt,
        public readonly string $updatedAt,
        public readonly ?string $finishedAt,
        public readonly ?string $importedAt,
        public readonly string $mode,
        public readonly string $status,
        public readonly ?string $decision,
        public readonly string $sourceKey,
        public readonly string $sourceLabel,
        public readonly string $documentKind,
        public readonly FileIdentity $file,
        public readonly int $policyVersion,
        public readonly int $analyzerSchemaVersion,
        public readonly array $metrics,
        public readonly array $samples,
        public readonly array $warnings,
        public readonly ?string $errorCode,
        public readonly ?string $errorMessage,
        public readonly ?int $durationMs,
        public readonly ?int $memoryDeltaBytes,
        public readonly bool $dryRun,
        public readonly string $cacheUntil,
        public readonly array $ruleResults = [],
    ) {
    }

    public function withId(int $id): self
    {
        return new self(
            $id,
            $this->createdAt,
            $this->updatedAt,
            $this->finishedAt,
            $this->importedAt,
            $this->mode,
            $this->status,
            $this->decision,
            $this->sourceKey,
            $this->sourceLabel,
            $this->documentKind,
            $this->file,
            $this->policyVersion,
            $this->analyzerSchemaVersion,
            $this->metrics,
            $this->samples,
            $this->warnings,
            $this->errorCode,
            $this->errorMessage,
            $this->durationMs,
            $this->memoryDeltaBytes,
            $this->dryRun,
            $this->cacheUntil,
            $this->ruleResults,
        );
    }

    public function imported(string $at): self
    {
        return new self(
            $this->id,
            $this->createdAt,
            $at,
            $this->finishedAt,
            $at,
            $this->mode,
            'IMPORTED',
            $this->decision,
            $this->sourceKey,
            $this->sourceLabel,
            $this->documentKind,
            $this->file,
            $this->policyVersion,
            $this->analyzerSchemaVersion,
            $this->metrics,
            $this->samples,
            $this->warnings,
            $this->errorCode,
            $this->errorMessage,
            $this->durationMs,
            $this->memoryDeltaBytes,
            $this->dryRun,
            $this->cacheUntil,
            $this->ruleResults,
        );
    }
}
