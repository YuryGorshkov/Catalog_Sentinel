<?php

declare(strict_types=1);

use Bitrix\Main\Config\Option;
use Bitrix\Main\EventManager;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Gorshkov\CatalogSentinel\Bitrix\Agent\CleanupAgent;
use Gorshkov\CatalogSentinel\Bitrix\Event\CatalogImportEventHandler;
use Gorshkov\CatalogSentinel\Bitrix\Event\CatalogImportSuccessEventHandler;

Loc::loadMessages(__FILE__);

class gorshkov_catalogsentinel extends CModule
{
    public const MODULE_ID_VALUE = 'gorshkov.catalogsentinel';
    public $MODULE_ID = self::MODULE_ID_VALUE;
    public $MODULE_VERSION;
    public $MODULE_VERSION_DATE;
    public $MODULE_NAME;
    public $MODULE_DESCRIPTION;
    public $PARTNER_NAME = 'Catalog Sentinel contributors';
    public $PARTNER_URI = '';
    public $MODULE_GROUP_RIGHTS = 'Y';

    public function __construct()
    {
        $version = [];
        include __DIR__ . '/version.php';
        if (isset($arModuleVersion) && is_array($arModuleVersion)) {
            $version = $arModuleVersion;
        }
        $this->MODULE_VERSION = (string) ($version['VERSION'] ?? '1.0.0');
        $this->MODULE_VERSION_DATE = (string) ($version['VERSION_DATE'] ?? '2026-09-22 00:00:00');
        $this->MODULE_NAME = Loc::getMessage('GCS_MODULE_NAME') ?: 'Catalog Sentinel';
        $this->MODULE_DESCRIPTION = Loc::getMessage('GCS_MODULE_DESCRIPTION') ?: 'CommerceML import safety guard.';
    }

    public function DoInstall(): void
    {
        global $APPLICATION;
        try {
            $this->assertRequirements();
            RegisterModule($this->MODULE_ID);
            $this->InstallDB();
            $this->InstallEvents();
            $this->InstallFiles();
            $this->installDefaults();
            $APPLICATION->IncludeAdminFile(
                Loc::getMessage('GCS_INSTALL_TITLE') ?: 'Catalog Sentinel installation',
                __DIR__ . '/step.php',
            );
        } catch (Throwable $exception) {
            $this->rollbackInstallation();
            $APPLICATION->ThrowException($exception->getMessage());
        }
    }

    public function DoUninstall(): void
    {
        global $APPLICATION;
        $step = (int) ($_REQUEST['step'] ?? 1);
        if ($step < 2) {
            $APPLICATION->IncludeAdminFile(
                Loc::getMessage('GCS_UNINSTALL_TITLE') ?: 'Catalog Sentinel removal',
                __DIR__ . '/unstep1.php',
            );

            return;
        }
        if (!check_bitrix_sessid()) {
            throw new RuntimeException('Invalid CSRF token.');
        }
        $deleteData = ($_POST['delete_data'] ?? 'N') === 'Y';
        $this->UnInstallEvents();
        $this->UnInstallFiles();
        if ($deleteData) {
            $this->UnInstallDB(['delete_data' => 'Y']);
            Option::delete($this->MODULE_ID);
        }
        UnRegisterModule($this->MODULE_ID);
        $APPLICATION->IncludeAdminFile(
            Loc::getMessage('GCS_UNINSTALL_TITLE') ?: 'Catalog Sentinel removal',
            __DIR__ . '/unstep2.php',
        );
    }

    public function InstallDB($arParams = []): bool
    {
        global $DB;
        $errors = $DB->RunSQLBatch(__DIR__ . '/db/mysql/install.sql');
        if ($errors !== false) {
            throw new RuntimeException(implode('; ', $errors));
        }

        return true;
    }

    public function UnInstallDB($arParams = []): bool
    {
        global $DB;
        if (($arParams['delete_data'] ?? 'N') !== 'Y') {
            return true;
        }
        $errors = $DB->RunSQLBatch(__DIR__ . '/db/mysql/uninstall.sql');
        if ($errors !== false) {
            throw new RuntimeException(implode('; ', $errors));
        }

        return true;
    }

    public function InstallEvents(): bool
    {
        $events = EventManager::getInstance();
        $this->unregisterHandlers($events);
        $events->registerEventHandler('catalog', 'OnBeforeCatalogImport1C', $this->MODULE_ID, CatalogImportEventHandler::class, 'onBefore');
        $events->registerEventHandler('catalog', 'OnSuccessCatalogImport1C', $this->MODULE_ID, CatalogImportSuccessEventHandler::class, 'onSuccess');
        CAgent::RemoveAgent(CleanupAgent::class . '::run();', $this->MODULE_ID);
        CAgent::AddAgent(CleanupAgent::class . '::run();', $this->MODULE_ID, 'N', 86400);
        $this->installMailEvent();

        return true;
    }

    public function UnInstallEvents(): bool
    {
        $this->unregisterHandlers(EventManager::getInstance());
        CAgent::RemoveAgent(CleanupAgent::class . '::run();', $this->MODULE_ID);
        $this->uninstallMailEvent();

        return true;
    }

    public function InstallFiles($arParams = []): bool
    {
        CopyDirFiles(__DIR__ . '/admin', $_SERVER['DOCUMENT_ROOT'] . '/bitrix/admin', true, true);

        return true;
    }

    public function UnInstallFiles(): bool
    {
        foreach (glob(__DIR__ . '/admin/*.php') ?: [] as $file) {
            DeleteFileEx('/bitrix/admin/' . basename($file));
        }

        return true;
    }

    private function assertRequirements(): void
    {
        if (PHP_VERSION_ID < 80100) {
            throw new RuntimeException('Catalog Sentinel requires PHP 8.1 or newer.');
        }
        if (!class_exists(XMLReader::class)) {
            throw new RuntimeException('Catalog Sentinel requires ext-xml/XMLReader.');
        }
        foreach (['main', 'iblock', 'catalog'] as $module) {
            if (!Loader::includeModule($module)) {
                throw new RuntimeException('Required Bitrix module is unavailable: ' . $module);
            }
        }
    }

    private function unregisterHandlers(EventManager $events): void
    {
        $events->unRegisterEventHandler('catalog', 'OnBeforeCatalogImport1C', $this->MODULE_ID, CatalogImportEventHandler::class, 'onBefore');
        $events->unRegisterEventHandler('catalog', 'OnSuccessCatalogImport1C', $this->MODULE_ID, CatalogImportSuccessEventHandler::class, 'onSuccess');
    }

    private function installDefaults(): void
    {
        $defaults = [];
        include dirname(__DIR__) . '/default_option.php';
        if (isset($gorshkov_catalogsentinel_default_option) && is_array($gorshkov_catalogsentinel_default_option)) {
            $defaults = $gorshkov_catalogsentinel_default_option;
        }
        foreach ($defaults as $name => $value) {
            if (Option::get($this->MODULE_ID, (string) $name, '') === '') {
                Option::set($this->MODULE_ID, (string) $name, (string) $value);
            }
        }
    }

    private function installMailEvent(): void
    {
        foreach (['ru', 'en'] as $language) {
            $existing = CEventType::GetList(['EVENT_NAME' => 'GCS_CATALOG_SENTINEL_BLOCKED', 'LID' => $language])->Fetch();
            if (!$existing) {
                (new CEventType())->Add([
                    'LID' => $language,
                    'EVENT_NAME' => 'GCS_CATALOG_SENTINEL_BLOCKED',
                    'NAME' => 'Catalog Sentinel: blocked CommerceML file',
                    'DESCRIPTION' => '#SCAN_ID# #FILE_NAME# #DOCUMENT_KIND# #RULE_CODE# #ACTUAL# #THRESHOLDS#',
                ]);
            }
        }
        $by = 'id';
        $order = 'asc';
        $existingTemplate = CEventMessage::GetList($by, $order, ['TYPE_ID' => 'GCS_CATALOG_SENTINEL_BLOCKED'])->Fetch();
        if (!$existingTemplate) {
            (new CEventMessage())->Add([
                'ACTIVE' => 'Y',
                'EVENT_NAME' => 'GCS_CATALOG_SENTINEL_BLOCKED',
                'LID' => ['s1'],
                'EMAIL_FROM' => '#DEFAULT_EMAIL_FROM#',
                'EMAIL_TO' => '#EMAIL_TO#',
                'SUBJECT' => 'Catalog Sentinel: #FILE_NAME# blocked',
                'MESSAGE' => "Scan: #SCAN_ID#\nDocument: #DOCUMENT_KIND#\nRule: #RULE_CODE#\nActual: #ACTUAL#\nThresholds: #THRESHOLDS#",
            ]);
        }
    }

    private function uninstallMailEvent(): void
    {
        $by = 'id';
        $order = 'asc';
        $messages = CEventMessage::GetList($by, $order, ['TYPE_ID' => 'GCS_CATALOG_SENTINEL_BLOCKED']);
        while ($message = $messages->Fetch()) {
            CEventMessage::Delete((int) $message['ID']);
        }
        CEventType::Delete('GCS_CATALOG_SENTINEL_BLOCKED');
    }

    private function rollbackInstallation(): void
    {
        try {
            $this->UnInstallEvents();
            $this->UnInstallFiles();
            $this->UnInstallDB(['delete_data' => 'Y']);
        } catch (Throwable) {
        }
        UnRegisterModule($this->MODULE_ID);
    }
}
