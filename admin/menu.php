<?php

declare(strict_types=1);

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Gorshkov\CatalogSentinel\Bitrix\Admin\AdminGuard;

Loc::loadMessages(__FILE__);

if (!Loader::includeModule('gorshkov.catalogsentinel')) {
    return false;
}

if ($APPLICATION->GetGroupRight(AdminGuard::MODULE_ID) < 'R') {
    return false;
}

return [
    'parent_menu' => 'global_menu_store',
    'section' => 'gorshkov_catalogsentinel',
    'sort' => 450,
    'text' => Loc::getMessage('GCS_MENU_TITLE'),
    'title' => Loc::getMessage('GCS_MENU_TITLE'),
    'icon' => 'sale_menu_icon_statistic',
    'page_icon' => 'sale_page_icon',
    'items_id' => 'menu_gorshkov_catalogsentinel',
    'items' => [
        ['text' => Loc::getMessage('GCS_MENU_DASHBOARD'), 'url' => 'gorshkov_catalogsentinel_dashboard.php?lang=' . LANGUAGE_ID],
        ['text' => Loc::getMessage('GCS_MENU_SCANS'), 'url' => 'gorshkov_catalogsentinel_scans.php?lang=' . LANGUAGE_ID],
        ['text' => Loc::getMessage('GCS_MENU_LATEST'), 'url' => 'gorshkov_catalogsentinel_scan.php?lang=' . LANGUAGE_ID],
        ['text' => Loc::getMessage('GCS_MENU_BASELINES'), 'url' => 'gorshkov_catalogsentinel_baselines.php?lang=' . LANGUAGE_ID],
        ['text' => Loc::getMessage('GCS_MENU_DRY_RUN'), 'url' => 'gorshkov_catalogsentinel_dry_run.php?lang=' . LANGUAGE_ID],
        ['text' => Loc::getMessage('GCS_MENU_SETTINGS'), 'url' => 'settings.php?mid=gorshkov.catalogsentinel&lang=' . LANGUAGE_ID],
    ],
];
