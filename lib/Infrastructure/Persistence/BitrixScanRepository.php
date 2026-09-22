<?php

declare(strict_types=1);

namespace Gorshkov\CatalogSentinel\Infrastructure\Persistence;

use Bitrix\Main\Application;
use Bitrix\Main\Type\DateTime;
use Gorshkov\CatalogSentinel\Application\Contract\ScanRepositoryInterface;
use Gorshkov\CatalogSentinel\Application\DTO\FileIdentity;
use Gorshkov\CatalogSentinel\Application\DTO\ScanRecord;
use Gorshkov\CatalogSentinel\Domain\Rule\RuleOutcome;
use Gorshkov\CatalogSentinel\Domain\Rule\RuleResult;
use Gorshkov\CatalogSentinel\Domain\Scan\ScanStatus;
use Gorshkov\CatalogSentinel\Infrastructure\Persistence\ORM\ScanTable;
use Gorshkov\CatalogSentinel\Infrastructure\Persistence\ORM\ViolationTable;

final class BitrixScanRepository implements ScanRepositoryInterface
{
    public function __construct(private readonly JsonCodec $json = new JsonCodec())
    {
    }

    public function save(ScanRecord $scan): ScanRecord
    {
        $connection = Application::getConnection();
        $connection->startTransaction();
        try {
            $fields = $this->toFields($scan);
            if ($scan->id === null) {
                $result = ScanTable::add($fields);
                if (!$result->isSuccess()) {
                    throw new \RuntimeException(implode('; ', $result->getErrorMessages()));
                }
                $scan = $scan->withId((int) $result->getId());
            } else {
                $result = ScanTable::update($scan->id, $fields);
                if (!$result->isSuccess()) {
                    throw new \RuntimeException(implode('; ', $result->getErrorMessages()));
                }
            }
            foreach ($scan->ruleResults as $rule) {
                if (!in_array($rule->outcome, [RuleOutcome::WARN, RuleOutcome::BLOCK, RuleOutcome::ERROR], true)) {
                    continue;
                }
                $result = ViolationTable::add([
                    'SCAN_ID' => $scan->id,
                    'CREATED_AT' => $this->date($rule->evaluatedAt ?: $scan->updatedAt),
                    'RULE_CODE' => $rule->ruleCode,
                    'OUTCOME' => $rule->outcome,
                    'REASON_CODE' => $rule->reasonCode,
                    'ACTUAL_JSON' => $this->json->encode($rule->actual),
                    'THRESHOLDS_JSON' => $this->json->encode($rule->thresholds),
                    'BASELINE_JSON' => $this->json->encode($rule->baseline),
                    'SAMPLE_JSON' => $this->json->encode($rule->sampleIds),
                ]);
                if (!$result->isSuccess()) {
                    throw new \RuntimeException(implode('; ', $result->getErrorMessages()));
                }
            }
            $connection->commitTransaction();

            return $scan;
        } catch (\Throwable $exception) {
            $connection->rollbackTransaction();
            throw $exception;
        }
    }

    public function findCached(string $identityKey, int $policyVersion, \DateTimeImmutable $now): ?ScanRecord
    {
        $row = ScanTable::getList([
            'filter' => [
                '=IDENTITY_KEY' => $identityKey,
                '=POLICY_VERSION' => $policyVersion,
                '>CACHE_UNTIL' => DateTime::createFromPhp(\DateTime::createFromImmutable($now)),
                '=IS_DRY_RUN' => 'N',
            ],
            'order' => ['ID' => 'DESC'],
            'limit' => 1,
        ])->fetch();

        return is_array($row) ? $this->fromRow($row) : null;
    }

    public function findImportCandidates(string $identityKey, \DateTimeImmutable $since): array
    {
        $rows = ScanTable::getList([
            'filter' => [
                '=IDENTITY_KEY' => $identityKey,
                '@STATUS' => [ScanStatus::OBSERVED, ScanStatus::ALLOWED],
                '>=CREATED_AT' => DateTime::createFromPhp(\DateTime::createFromImmutable($since)),
                '=IS_DRY_RUN' => 'N',
            ],
            'order' => ['ID' => 'DESC'],
            'limit' => 2,
        ])->fetchAll();

        return array_map($this->fromRow(...), $rows);
    }

    public function markImported(int $scanId, \DateTimeImmutable $at): bool
    {
        $row = ScanTable::getByPrimary($scanId, ['select' => ['ID', 'STATUS']])->fetch();
        if (!is_array($row) || !in_array($row['STATUS'], [ScanStatus::OBSERVED, ScanStatus::ALLOWED], true)) {
            return false;
        }
        $result = ScanTable::update($scanId, [
            'STATUS' => ScanStatus::IMPORTED,
            'IMPORTED_AT' => DateTime::createFromPhp(\DateTime::createFromImmutable($at)),
            'UPDATED_AT' => DateTime::createFromPhp(\DateTime::createFromImmutable($at)),
        ]);

        return $result->isSuccess();
    }

    public function findById(int $scanId): ?ScanRecord
    {
        $row = ScanTable::getByPrimary($scanId)->fetch();

        return is_array($row) ? $this->fromRow($row) : null;
    }

    public function deleteOlderThan(\DateTimeImmutable $before, int $limit): int
    {
        $rows = ScanTable::getList([
            'select' => ['ID'],
            'filter' => ['<CREATED_AT' => DateTime::createFromPhp(\DateTime::createFromImmutable($before))],
            'order' => ['ID' => 'ASC'],
            'limit' => max(1, min(1000, $limit)),
        ])->fetchAll();
        $deleted = 0;
        foreach ($rows as $row) {
            $scanId = (int) $row['ID'];
            $violations = ViolationTable::getList(['select' => ['ID'], 'filter' => ['=SCAN_ID' => $scanId]])->fetchAll();
            foreach ($violations as $violation) {
                ViolationTable::delete((int) $violation['ID']);
            }
            if (ScanTable::delete($scanId)->isSuccess()) {
                ++$deleted;
            }
        }

        return $deleted;
    }

    /** @return array<string, mixed> */
    private function toFields(ScanRecord $scan): array
    {
        return [
            'CREATED_AT' => $this->date($scan->createdAt),
            'UPDATED_AT' => $this->date($scan->updatedAt),
            'FINISHED_AT' => $scan->finishedAt !== null ? $this->date($scan->finishedAt) : null,
            'IMPORTED_AT' => $scan->importedAt !== null ? $this->date($scan->importedAt) : null,
            'MODE' => $scan->mode,
            'STATUS' => $scan->status,
            'DECISION' => $scan->decision,
            'SOURCE_KEY' => $scan->sourceKey,
            'SOURCE_LABEL' => $scan->sourceLabel,
            'DOCUMENT_KIND' => $scan->documentKind,
            'FILE_NAME' => $scan->file->fileName,
            'PATH_HASH' => $scan->file->pathHash,
            'CONTENT_HASH' => $scan->file->contentHash,
            'FILE_SIZE' => $scan->file->size,
            'FILE_MTIME' => $scan->file->modifiedAt,
            'IDENTITY_KEY' => $scan->file->identityKey,
            'POLICY_VERSION' => $scan->policyVersion,
            'ANALYZER_SCHEMA_VERSION' => $scan->analyzerSchemaVersion,
            'METRICS_JSON' => $this->json->encode($scan->metrics),
            'SAMPLES_JSON' => $this->json->encode($scan->samples),
            'WARNINGS_JSON' => $this->json->encode($scan->warnings),
            'RULE_RESULTS_JSON' => $this->json->encode(array_map(
                static fn (RuleResult $rule): array => [
                    'rule_code' => $rule->ruleCode,
                    'outcome' => $rule->outcome,
                    'reason_code' => $rule->reasonCode,
                    'actual' => $rule->actual,
                    'thresholds' => $rule->thresholds,
                    'baseline' => $rule->baseline,
                    'sample_ids' => $rule->sampleIds,
                    'message_params' => $rule->messageParams,
                    'evaluated_at' => $rule->evaluatedAt,
                ],
                $scan->ruleResults,
            )),
            'ERROR_CODE' => $scan->errorCode,
            'ERROR_MESSAGE' => $scan->errorMessage,
            'DURATION_MS' => $scan->durationMs,
            'MEMORY_DELTA_BYTES' => $scan->memoryDeltaBytes,
            'IS_DRY_RUN' => $scan->dryRun ? 'Y' : 'N',
            'CACHE_UNTIL' => $this->date($scan->cacheUntil),
        ];
    }

    /** @param array<string, mixed> $row */
    private function fromRow(array $row): ScanRecord
    {
        $rules = [];
        foreach ($this->json->decode((string) ($row['RULE_RESULTS_JSON'] ?? '[]')) as $rule) {
            if (!is_array($rule)) {
                continue;
            }
            $rules[] = new RuleResult(
                (string) ($rule['rule_code'] ?? ''),
                (string) ($rule['outcome'] ?? ''),
                (string) ($rule['reason_code'] ?? ''),
                is_array($rule['actual'] ?? null) ? $rule['actual'] : [],
                is_array($rule['thresholds'] ?? null) ? $rule['thresholds'] : [],
                is_array($rule['baseline'] ?? null) ? $rule['baseline'] : [],
                is_array($rule['sample_ids'] ?? null) ? array_values($rule['sample_ids']) : [],
                is_array($rule['message_params'] ?? null) ? $rule['message_params'] : [],
                (string) ($rule['evaluated_at'] ?? ''),
            );
        }

        return new ScanRecord(
            (int) $row['ID'],
            $this->iso($row['CREATED_AT']),
            $this->iso($row['UPDATED_AT']),
            $this->nullableIso($row['FINISHED_AT'] ?? null),
            $this->nullableIso($row['IMPORTED_AT'] ?? null),
            (string) $row['MODE'],
            (string) $row['STATUS'],
            isset($row['DECISION']) ? (string) $row['DECISION'] : null,
            (string) $row['SOURCE_KEY'],
            (string) $row['SOURCE_LABEL'],
            (string) $row['DOCUMENT_KIND'],
            new FileIdentity(
                (string) $row['FILE_NAME'],
                (int) $row['FILE_SIZE'],
                isset($row['FILE_MTIME']) ? (int) $row['FILE_MTIME'] : null,
                (string) $row['PATH_HASH'],
                isset($row['CONTENT_HASH']) ? (string) $row['CONTENT_HASH'] : null,
                (string) $row['IDENTITY_KEY'],
            ),
            (int) $row['POLICY_VERSION'],
            (int) $row['ANALYZER_SCHEMA_VERSION'],
            $this->json->decode((string) $row['METRICS_JSON']),
            $this->json->decode((string) $row['SAMPLES_JSON']),
            $this->json->decode((string) $row['WARNINGS_JSON']),
            isset($row['ERROR_CODE']) ? (string) $row['ERROR_CODE'] : null,
            isset($row['ERROR_MESSAGE']) ? (string) $row['ERROR_MESSAGE'] : null,
            isset($row['DURATION_MS']) ? (int) $row['DURATION_MS'] : null,
            isset($row['MEMORY_DELTA_BYTES']) ? (int) $row['MEMORY_DELTA_BYTES'] : null,
            ($row['IS_DRY_RUN'] ?? 'N') === 'Y',
            $this->iso($row['CACHE_UNTIL']),
            $rules,
        );
    }

    private function date(string $value): DateTime
    {
        return DateTime::createFromPhp(new \DateTime($value));
    }

    private function iso(mixed $value): string
    {
        return $value instanceof \DateTimeInterface ? $value->format(DATE_ATOM) : (string) $value;
    }

    private function nullableIso(mixed $value): ?string
    {
        return $value === null ? null : $this->iso($value);
    }
}
