<?php

declare(strict_types=1);

namespace Gorshkov\CatalogSentinel\Bitrix\Admin;

use Bitrix\Main\Application;
use Bitrix\Main\EventManager;
use Gorshkov\CatalogSentinel\Infrastructure\Persistence\ORM\BaselineSampleTable;
use Gorshkov\CatalogSentinel\Infrastructure\Persistence\ORM\NotificationStateTable;
use Gorshkov\CatalogSentinel\Infrastructure\Persistence\ORM\ScanTable;
use Gorshkov\CatalogSentinel\Infrastructure\Persistence\ORM\ViolationTable;

final class Diagnostics
{
    /** @return array<string, string> */
    public function collect(): array
    {
        $connection = Application::getConnection();
        $tables = [ScanTable::TABLE_NAME, ViolationTable::TABLE_NAME, BaselineSampleTable::TABLE_NAME, NotificationStateTable::TABLE_NAME];
        $missing = array_filter($tables, static fn (string $table): bool => !$connection->isTableExists($table));
        $before = EventManager::getInstance()->findEventHandlers('catalog', 'OnBeforeCatalogImport1C');
        $success = EventManager::getInstance()->findEventHandlers('catalog', 'OnSuccessCatalogImport1C');
        $agent = \CAgent::GetList([], ['MODULE_ID' => AdminGuard::MODULE_ID, 'NAME' => '%CleanupAgent::run%'])->Fetch();

        return [
            'php' => PHP_VERSION,
            'bitrix' => defined('SM_VERSION') ? (string) SM_VERSION : 'unknown',
            'module' => '1.0.0',
            'xmlreader' => class_exists(\XMLReader::class) ? 'OK' : 'MISSING',
            'tables' => $missing === [] ? 'OK' : 'MISSING: ' . implode(', ', $missing),
            'before_handler' => $before !== [] ? 'OK' : 'MISSING',
            'success_handler' => $success !== [] ? 'OK' : 'MISSING',
            'cleanup_agent' => $agent ? 'OK' : 'MISSING',
            'event_log' => class_exists('CEventLog') ? 'OK' : 'MISSING',
        ];
    }
}
