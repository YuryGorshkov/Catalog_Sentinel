<?php

declare(strict_types=1);

namespace Gorshkov\CatalogSentinel\Infrastructure\Notification;

use Bitrix\Main\Config\Option;
use Bitrix\Main\Context;
use Bitrix\Main\Mail\Event;
use Bitrix\Main\Type\DateTime;
use Gorshkov\CatalogSentinel\Application\Contract\ClockInterface;
use Gorshkov\CatalogSentinel\Application\Contract\NotifierInterface;
use Gorshkov\CatalogSentinel\Application\DTO\ScanRecord;
use Gorshkov\CatalogSentinel\Domain\Rule\RuleOutcome;
use Gorshkov\CatalogSentinel\Infrastructure\Persistence\ORM\NotificationStateTable;

final class BitrixNotifier implements NotifierInterface
{
    private const MODULE_ID = 'gorshkov.catalogsentinel';

    public function __construct(private readonly ClockInterface $clock)
    {
    }

    public function notifyBlocked(ScanRecord $scan): void
    {
        foreach ($scan->ruleResults as $rule) {
            if ($rule->outcome !== RuleOutcome::BLOCK || !$this->canSend($scan, $rule->ruleCode, 'MAIL')) {
                continue;
            }
            $emails = Option::get(self::MODULE_ID, 'notification_emails', '');
            if (trim($emails) !== '') {
                Event::send([
                    'EVENT_NAME' => 'GCS_CATALOG_SENTINEL_BLOCKED',
                    'LID' => Context::getCurrent()->getSite() ?: 's1',
                    'C_FIELDS' => [
                        'EMAIL_TO' => $emails,
                        'SCAN_ID' => (string) ($scan->id ?? ''),
                        'FILE_NAME' => $scan->file->fileName,
                        'DOCUMENT_KIND' => $scan->documentKind,
                        'RULE_CODE' => $rule->ruleCode,
                        'ACTUAL' => json_encode($rule->actual, JSON_UNESCAPED_UNICODE),
                        'THRESHOLDS' => json_encode($rule->thresholds, JSON_UNESCAPED_UNICODE),
                    ],
                ]);
                $this->remember($scan, $rule->ruleCode, 'MAIL');
            }
            if ($this->canSend($scan, $rule->ruleCode, 'ADMIN') && class_exists('CAdminNotify')) {
                \CAdminNotify::Add([
                    'MESSAGE' => sprintf('Catalog Sentinel: scan #%s blocked by %s.', (string) ($scan->id ?? '?'), $rule->ruleCode),
                    'TAG' => 'GCS_' . $scan->sourceKey . '_' . $rule->ruleCode,
                    'MODULE_ID' => self::MODULE_ID,
                    'ENABLE_CLOSE' => 'Y',
                ]);
                $this->remember($scan, $rule->ruleCode, 'ADMIN');
            }
        }
    }

    private function canSend(ScanRecord $scan, string $ruleCode, string $channel): bool
    {
        $row = NotificationStateTable::getList([
            'filter' => ['=SOURCE_KEY' => $scan->sourceKey, '=RULE_CODE' => $ruleCode, '=CHANNEL' => $channel],
            'limit' => 1,
        ])->fetch();
        if (!is_array($row)) {
            return true;
        }
        $lastSent = $row['LAST_SENT_AT'];
        if (!$lastSent instanceof \DateTimeInterface) {
            return true;
        }
        $cooldown = max(0, (int) Option::get(self::MODULE_ID, 'notification_cooldown_minutes', '15'));

        return $lastSent->getTimestamp() + ($cooldown * 60) <= $this->clock->now()->getTimestamp();
    }

    private function remember(ScanRecord $scan, string $ruleCode, string $channel): void
    {
        $row = NotificationStateTable::getList([
            'select' => ['ID'],
            'filter' => ['=SOURCE_KEY' => $scan->sourceKey, '=RULE_CODE' => $ruleCode, '=CHANNEL' => $channel],
            'limit' => 1,
        ])->fetch();
        $fields = [
            'SOURCE_KEY' => $scan->sourceKey,
            'RULE_CODE' => $ruleCode,
            'CHANNEL' => $channel,
            'LAST_SENT_AT' => DateTime::createFromPhp(\DateTime::createFromImmutable($this->clock->now())),
            'LAST_SCAN_ID' => $scan->id ?? 0,
        ];
        if (is_array($row)) {
            NotificationStateTable::update((int) $row['ID'], $fields);
        } else {
            NotificationStateTable::add($fields);
        }
    }
}
