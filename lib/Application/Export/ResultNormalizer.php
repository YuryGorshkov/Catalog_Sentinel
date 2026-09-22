<?php

declare(strict_types=1);

namespace Gorshkov\CatalogSentinel\Application\Export;

use Gorshkov\CatalogSentinel\Domain\Analysis\AnalysisResult;
use Gorshkov\CatalogSentinel\Domain\Rule\RuleEvaluation;
use Gorshkov\CatalogSentinel\Domain\Rule\RuleResult;

final class ResultNormalizer
{
    /** @return array<string, mixed> */
    public function analysis(AnalysisResult $result): array
    {
        return [
            'document_kind' => $result->documentKind,
            'supported' => $result->supported,
            'well_formed' => $result->wellFormed,
            'metrics' => $result->metrics->all(),
            'samples' => $result->samples,
            'value_errors' => $result->valueErrors,
            'parser_warnings' => $result->parserWarnings,
            'source_hints' => $result->sourceHints,
            'duration_ms' => $result->durationMs,
            'peak_memory_delta_bytes' => $result->peakMemoryDeltaBytes,
            'document_metadata' => $result->documentMetadata,
            'error_code' => $result->errorCode,
        ];
    }

    /** @return array<string, mixed> */
    public function evaluation(RuleEvaluation $evaluation): array
    {
        return [
            'decision' => $evaluation->decision,
            'rules' => array_map($this->rule(...), $evaluation->results),
        ];
    }

    /** @return array<string, mixed> */
    public function rule(RuleResult $result): array
    {
        return [
            'rule_code' => $result->ruleCode,
            'outcome' => $result->outcome,
            'reason_code' => $result->reasonCode,
            'actual' => $result->actual,
            'thresholds' => $result->thresholds,
            'baseline' => $result->baseline,
            'sample_ids' => $result->sampleIds,
            'message_params' => $result->messageParams,
            'evaluated_at' => $result->evaluatedAt,
        ];
    }
}
