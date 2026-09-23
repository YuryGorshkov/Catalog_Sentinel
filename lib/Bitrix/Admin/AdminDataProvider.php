<?php

declare(strict_types=1);

namespace Gorshkov\CatalogSentinel\Bitrix\Admin;

use Bitrix\Main\Type\DateTime;
use Gorshkov\CatalogSentinel\Application\DTO\FileIdentity;
use Gorshkov\CatalogSentinel\Application\DTO\ScanRecord;
use Gorshkov\CatalogSentinel\Domain\Rule\RuleResult;
use Gorshkov\CatalogSentinel\Domain\Baseline\BaselineSnapshot;
use Gorshkov\CatalogSentinel\Infrastructure\Persistence\JsonCodec;
use Gorshkov\CatalogSentinel\Infrastructure\Persistence\ORM\BaselineSampleTable;
use Gorshkov\CatalogSentinel\Infrastructure\Persistence\ORM\ScanTable;
use Gorshkov\CatalogSentinel\Infrastructure\Persistence\ORM\ViolationTable;

final class AdminDataProvider
{
    public function __construct(private readonly JsonCodec $json = new JsonCodec())
    {
    }

    /** @return array<string, int> */
    public function summary(\DateTimeImmutable $since): array
    {
        $date = DateTime::createFromPhp(\DateTime::createFromImmutable($since));

        return [
            'scans' => ScanTable::getCount(['>=CREATED_AT' => $date]),
            'blocked' => ScanTable::getCount(['>=CREATED_AT' => $date, '=DECISION' => 'BLOCK']),
            'warnings' => ScanTable::getCount(['>=CREATED_AT' => $date, '=DECISION' => 'WARN']),
            'errors' => ScanTable::getCount(['>=CREATED_AT' => $date, '=DECISION' => 'ERROR']),
        ];
    }

    /** @return list<array<string, mixed>> */
    public function recentBlocks(int $limit = 5): array
    {
        return ScanTable::getList([
            'select' => ['ID', 'CREATED_AT', 'SOURCE_LABEL', 'DOCUMENT_KIND', 'FILE_NAME', 'STATUS', 'DECISION'],
            'filter' => ['=DECISION' => 'BLOCK'],
            'order' => ['ID' => 'DESC'],
            'limit' => max(1, min(20, $limit)),
        ])->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function latestImported(): ?array
    {
        $row = ScanTable::getList([
            'select' => ['ID', 'IMPORTED_AT', 'SOURCE_LABEL', 'DOCUMENT_KIND', 'FILE_NAME'],
            'filter' => ['=STATUS' => 'IMPORTED'],
            'order' => ['IMPORTED_AT' => 'DESC'],
            'limit' => 1,
        ])->fetch();

        return is_array($row) ? $row : null;
    }

    public function latestScanId(): ?int
    {
        $row = ScanTable::getList([
            'select' => ['ID'],
            'order' => ['ID' => 'DESC'],
            'limit' => 1,
        ])->fetch();

        return is_array($row) ? (int) $row['ID'] : null;
    }

    /**
     * @param array<string, string|int> $filters
     * @return array{rows: list<array<string, mixed>>, total: int}
     */
    public function scans(array $filters, int $offset, int $limit): array
    {
        $filter = [];
        foreach (['STATUS', 'DECISION', 'DOCUMENT_KIND', 'SOURCE_KEY', 'IS_DRY_RUN'] as $field) {
            if (isset($filters[$field]) && $filters[$field] !== '') {
                $filter['=' . $field] = $filters[$field];
            }
        }
        if (!empty($filters['DATE_FROM'])) {
            $filter['>=CREATED_AT'] = new DateTime((string) $filters['DATE_FROM'] . ' 00:00:00');
        }
        if (!empty($filters['DATE_TO'])) {
            $filter['<=CREATED_AT'] = new DateTime((string) $filters['DATE_TO'] . ' 23:59:59');
        }
        if (!empty($filters['RULE_CODE'])) {
            $violations = ViolationTable::getList([
                'select' => ['SCAN_ID'],
                'filter' => ['=RULE_CODE' => (string) $filters['RULE_CODE']],
                'limit' => 5000,
            ])->fetchAll();
            $ids = array_map(static fn (array $row): int => (int) $row['SCAN_ID'], $violations);
            $filter['@ID'] = $ids !== [] ? $ids : [-1];
        }
        $rows = ScanTable::getList([
            'select' => [
                'ID',
                'CREATED_AT',
                'SOURCE_LABEL',
                'DOCUMENT_KIND',
                'FILE_NAME',
                'MODE',
                'STATUS',
                'DECISION',
                'METRICS_JSON',
                'DURATION_MS',
                'IS_DRY_RUN',
            ],
            'filter' => $filter,
            'order' => ['ID' => 'DESC'],
            'offset' => max(0, $offset),
            'limit' => max(1, min(200, $limit)),
        ])->fetchAll();
        foreach ($rows as &$row) {
            $metrics = $this->json->decode((string) $row['METRICS_JSON']);
            $row['OBJECTS_TOTAL'] = $metrics['document.objects_total'] ?? null;
            unset($row['METRICS_JSON']);
        }
        unset($row);

        return ['rows' => $rows, 'total' => ScanTable::getCount($filter)];
    }

    public function scan(int $id): ?ScanRecord
    {
        $row = ScanTable::getByPrimary($id)->fetch();
        if (!is_array($row)) {
            return null;
        }
        $rules = [];
        foreach ($this->json->decode((string) ($row['RULE_RESULTS_JSON'] ?? '[]')) as $violation) {
            if (!is_array($violation)) {
                continue;
            }
            $rules[] = new RuleResult(
                (string) ($violation['rule_code'] ?? ''),
                (string) ($violation['outcome'] ?? ''),
                (string) ($violation['reason_code'] ?? ''),
                is_array($violation['actual'] ?? null) ? $violation['actual'] : [],
                is_array($violation['thresholds'] ?? null) ? $violation['thresholds'] : [],
                is_array($violation['baseline'] ?? null) ? $violation['baseline'] : [],
                is_array($violation['sample_ids'] ?? null) ? array_values($violation['sample_ids']) : [],
                is_array($violation['message_params'] ?? null) ? $violation['message_params'] : [],
                (string) ($violation['evaluated_at'] ?? ''),
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

    /** @return list<array<string, mixed>> */
    public function baselines(int $minimumSamples, int $window = 5): array
    {
        $rows = BaselineSampleTable::getList(['order' => ['CREATED_AT' => 'ASC', 'ID' => 'ASC']])->fetchAll();
        $groups = [];
        foreach ($rows as $row) {
            $key = $row['SOURCE_KEY'] . '|' . $row['DOCUMENT_KIND'] . '|' . $row['ANALYZER_SCHEMA_VERSION'];
            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'SOURCE_KEY' => $row['SOURCE_KEY'],
                    'DOCUMENT_KIND' => $row['DOCUMENT_KIND'],
                    'ANALYZER_SCHEMA_VERSION' => (int) $row['ANALYZER_SCHEMA_VERSION'],
                    'FIRST_AT' => $row['CREATED_AT'],
                    'LAST_AT' => $row['CREATED_AT'],
                    'SCAN_IDS' => [],
                    'SAMPLES' => [],
                ];
            }
            $groups[$key]['LAST_AT'] = $row['CREATED_AT'];
            $groups[$key]['SCAN_IDS'][] = (int) $row['SCAN_ID'];
            $groups[$key]['SAMPLES'][] = $this->json->decode((string) $row['METRICS_JSON']);
        }
        foreach ($groups as &$group) {
            $group['COUNT'] = count($group['SAMPLES']);
            $group['STATUS'] = $group['COUNT'] >= $minimumSamples ? 'READY' : 'NOT_READY';
            $snapshot = BaselineSnapshot::fromSamples(
                (string) $group['SOURCE_KEY'],
                (string) $group['DOCUMENT_KIND'],
                (int) $group['ANALYZER_SCHEMA_VERSION'],
                $minimumSamples,
                $window,
                $group['SAMPLES'],
            );
            $group['MEDIANS'] = $snapshot->medians();
        }
        unset($group);

        return array_values($groups);
    }

    /** @return array<string, string> */
    public function sourceOptions(): array
    {
        $rows = ScanTable::getList([
            'select' => ['SOURCE_KEY', 'SOURCE_LABEL'],
            'order' => ['ID' => 'DESC'],
            'limit' => 1000,
        ])->fetchAll();
        $options = [];
        foreach ($rows as $row) {
            $options[(string) $row['SOURCE_KEY']] = (string) $row['SOURCE_LABEL'];
        }

        return $options;
    }

    public function deleteScan(int $id): bool
    {
        foreach (ViolationTable::getList(['select' => ['ID'], 'filter' => ['=SCAN_ID' => $id]])->fetchAll() as $row) {
            ViolationTable::delete((int) $row['ID']);
        }
        foreach (BaselineSampleTable::getList(['select' => ['ID'], 'filter' => ['=SCAN_ID' => $id]])->fetchAll() as $row) {
            BaselineSampleTable::delete((int) $row['ID']);
        }

        return ScanTable::delete($id)->isSuccess();
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
