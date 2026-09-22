<?php

declare(strict_types=1);

namespace Gorshkov\CatalogSentinel\Infrastructure\Persistence;

use Bitrix\Main\Type\DateTime;
use Gorshkov\CatalogSentinel\Application\Contract\BaselineRepositoryInterface;
use Gorshkov\CatalogSentinel\Application\DTO\ScanRecord;
use Gorshkov\CatalogSentinel\Application\Baseline\BaselineMetricFilter;
use Gorshkov\CatalogSentinel\Domain\Baseline\BaselineSnapshot;
use Gorshkov\CatalogSentinel\Domain\Policy\PolicySnapshot;
use Gorshkov\CatalogSentinel\Infrastructure\Persistence\ORM\BaselineSampleTable;

final class BitrixBaselineRepository implements BaselineRepositoryInterface
{
    public function __construct(private readonly JsonCodec $json = new JsonCodec())
    {
    }

    public function get(string $sourceKey, string $documentKind, PolicySnapshot $policy): BaselineSnapshot
    {
        $rows = BaselineSampleTable::getList([
            'select' => ['METRICS_JSON'],
            'filter' => [
                '=SOURCE_KEY' => $sourceKey,
                '=DOCUMENT_KIND' => $documentKind,
                '=ANALYZER_SCHEMA_VERSION' => $policy->analyzerSchemaVersion,
            ],
            'order' => ['CREATED_AT' => 'DESC', 'ID' => 'DESC'],
            'limit' => $policy->baselineWindow,
        ])->fetchAll();
        $samples = [];
        foreach (array_reverse($rows) as $row) {
            $samples[] = $this->json->decode((string) $row['METRICS_JSON']);
        }

        return BaselineSnapshot::fromSamples(
            $sourceKey,
            $documentKind,
            $policy->analyzerSchemaVersion,
            $policy->baselineMinSamples,
            $policy->baselineWindow,
            $samples,
        );
    }

    public function addSample(ScanRecord $scan, string $origin, ?int $acceptedBy = null): bool
    {
        if ($scan->id === null) {
            return false;
        }
        $existing = BaselineSampleTable::getList([
            'select' => ['ID'],
            'filter' => ['=SCAN_ID' => $scan->id, '=ORIGIN' => $origin],
            'limit' => 1,
        ])->fetch();
        if ($existing !== false) {
            return false;
        }
        $result = BaselineSampleTable::add([
            'CREATED_AT' => new DateTime(),
            'SOURCE_KEY' => $scan->sourceKey,
            'DOCUMENT_KIND' => $scan->documentKind,
            'ANALYZER_SCHEMA_VERSION' => $scan->analyzerSchemaVersion,
            'SCAN_ID' => $scan->id,
            'ORIGIN' => $origin,
            'ACCEPTED_BY' => $acceptedBy,
            'METRICS_JSON' => $this->json->encode((new BaselineMetricFilter())->filter($scan->metrics)),
        ]);

        return $result->isSuccess();
    }

    public function reset(string $sourceKey, string $documentKind, int $schemaVersion): int
    {
        $rows = BaselineSampleTable::getList([
            'select' => ['ID'],
            'filter' => [
                '=SOURCE_KEY' => $sourceKey,
                '=DOCUMENT_KIND' => $documentKind,
                '=ANALYZER_SCHEMA_VERSION' => $schemaVersion,
            ],
        ])->fetchAll();
        $deleted = 0;
        foreach ($rows as $row) {
            if (BaselineSampleTable::delete((int) $row['ID'])->isSuccess()) {
                ++$deleted;
            }
        }

        return $deleted;
    }

    public function cleanup(int $retentionDays, int $window): int
    {
        $cutoff = (new \DateTimeImmutable())->sub(new \DateInterval('P' . $retentionDays . 'D'));
        $rows = BaselineSampleTable::getList([
            'select' => ['ID', 'CREATED_AT', 'SOURCE_KEY', 'DOCUMENT_KIND', 'ANALYZER_SCHEMA_VERSION'],
            'order' => ['SOURCE_KEY' => 'ASC', 'DOCUMENT_KIND' => 'ASC', 'ANALYZER_SCHEMA_VERSION' => 'ASC', 'CREATED_AT' => 'DESC'],
        ])->fetchAll();
        $positions = [];
        $deleted = 0;
        foreach ($rows as $row) {
            $group = $row['SOURCE_KEY'] . '|' . $row['DOCUMENT_KIND'] . '|' . $row['ANALYZER_SCHEMA_VERSION'];
            $positions[$group] = ($positions[$group] ?? 0) + 1;
            $created = $row['CREATED_AT'];
            if ($positions[$group] > $window && $created instanceof \DateTimeInterface && $created < $cutoff) {
                if (BaselineSampleTable::delete((int) $row['ID'])->isSuccess()) {
                    ++$deleted;
                }
            }
        }

        return $deleted;
    }
}
