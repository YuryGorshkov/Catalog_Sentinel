<?php

declare(strict_types=1);

use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Gorshkov\CatalogSentinel\Bitrix\Admin\AdminDataProvider;
use Gorshkov\CatalogSentinel\Bitrix\Admin\AdminGuard;

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php';
Loader::includeModule(AdminGuard::MODULE_ID) || die();
Loc::loadMessages(__FILE__);
AdminGuard::requireRead();
$APPLICATION->SetTitle((string) Loc::getMessage('GCS_DASHBOARD_TITLE'));
$provider = new AdminDataProvider();
$day = $provider->summary(new DateTimeImmutable('-24 hours'));
$week = $provider->summary(new DateTimeImmutable('-7 days'));
$blocks = $provider->recentBlocks();
$latestImported = $provider->latestImported();
$baselines = $provider->baselines((int) Option::get(AdminGuard::MODULE_ID, 'baseline_min_samples', '3'), (int) Option::get(AdminGuard::MODULE_ID, 'baseline_window', '5'));
$mode = Option::get(AdminGuard::MODULE_ID, 'mode', 'OBSERVE');
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';
?>
<div class="adm-info-message"><?= htmlspecialcharsbx((string) Loc::getMessage('GCS_FILE_SCOPE_NOTICE')) ?></div>
<h2><?= htmlspecialcharsbx((string) Loc::getMessage('GCS_MODE')) ?>: <?= htmlspecialcharsbx($mode) ?></h2>
<?php if ($mode === 'PROTECT' && array_filter($baselines, static fn (array $item): bool => $item['STATUS'] === 'READY') === []): ?><div class="adm-info-message adm-info-message-red"><?= htmlspecialcharsbx((string) Loc::getMessage('GCS_PROTECT_WITHOUT_BASELINE')) ?></div><?php endif; ?>
<table class="adm-list-table">
    <thead><tr class="adm-list-table-header"><td></td><td>24h</td><td>7d</td></tr></thead>
    <tbody>
    <?php foreach (['scans', 'blocked', 'warnings', 'errors'] as $metric): ?>
        <tr><td><?= htmlspecialcharsbx((string) Loc::getMessage('GCS_METRIC_' . strtoupper($metric))) ?></td><td><?= (int) $day[$metric] ?></td><td><?= (int) $week[$metric] ?></td></tr>
    <?php endforeach; ?>
    </tbody>
</table>
<h2><?= htmlspecialcharsbx((string) Loc::getMessage('GCS_RECENT_BLOCKS')) ?></h2>
<table class="adm-list-table">
    <thead><tr class="adm-list-table-header"><td>ID</td><td><?= htmlspecialcharsbx((string) Loc::getMessage('GCS_DATE')) ?></td><td><?= htmlspecialcharsbx((string) Loc::getMessage('GCS_SOURCE')) ?></td><td><?= htmlspecialcharsbx((string) Loc::getMessage('GCS_FILE')) ?></td></tr></thead>
    <tbody>
    <?php foreach ($blocks as $row): ?>
        <tr><td><a href="gorshkov_catalogsentinel_scan.php?id=<?= (int) $row['ID'] ?>&amp;lang=<?= urlencode(LANGUAGE_ID) ?>"><?= (int) $row['ID'] ?></a></td><td><?= htmlspecialcharsbx((string) $row['CREATED_AT']) ?></td><td><?= htmlspecialcharsbx((string) $row['SOURCE_LABEL']) ?></td><td><?= htmlspecialcharsbx((string) $row['FILE_NAME']) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
</table>
<h2><?= htmlspecialcharsbx((string) Loc::getMessage('GCS_LATEST_IMPORTED')) ?></h2>
<p><?= $latestImported !== null ? htmlspecialcharsbx('#' . $latestImported['ID'] . ' ' . $latestImported['FILE_NAME']) : htmlspecialcharsbx((string) Loc::getMessage('GCS_NONE')) ?></p>
<h2><?= htmlspecialcharsbx((string) Loc::getMessage('GCS_BASELINE_STATUS')) ?></h2>
<table class="adm-list-table"><tbody><?php foreach ($baselines as $baseline): ?><tr><td><?= htmlspecialcharsbx((string) $baseline['SOURCE_KEY']) ?></td><td><?= htmlspecialcharsbx((string) $baseline['DOCUMENT_KIND']) ?></td><td><?= htmlspecialcharsbx((string) $baseline['STATUS']) ?></td><td><?= (int) $baseline['COUNT'] ?></td></tr><?php endforeach; ?></tbody></table>
<?php require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php'; ?>
