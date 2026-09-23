<?php

declare(strict_types=1);

use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Gorshkov\CatalogSentinel\Bitrix\Admin\AdminDataProvider;
use Gorshkov\CatalogSentinel\Bitrix\Admin\AdminGuard;

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php';
Loader::includeModule('gorshkov.catalogsentinel') || die();
Loc::loadMessages(__FILE__);
AdminGuard::requireRead();

$APPLICATION->SetTitle((string) Loc::getMessage('GCS_DASHBOARD_TITLE'));
$provider = new AdminDataProvider();
$day = $provider->summary(new DateTimeImmutable('-24 hours'));
$week = $provider->summary(new DateTimeImmutable('-7 days'));
$blocks = $provider->recentBlocks();
$latestImported = $provider->latestImported();
$minimumSamples = (int) Option::get(AdminGuard::MODULE_ID, 'baseline_min_samples', '3');
$window = (int) Option::get(AdminGuard::MODULE_ID, 'baseline_window', '5');
$baselines = $provider->baselines($minimumSamples, $window);
$sources = $provider->sourceOptions();
$mode = Option::get(AdminGuard::MODULE_ID, 'mode', 'OBSERVE');
$readyBaselines = array_filter($baselines, static fn (array $item): bool => $item['STATUS'] === 'READY');
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';

$escape = static fn (mixed $value): string => htmlspecialcharsbx((string) $value);
$formatDate = static function (mixed $value): string {
    try {
        return (new DateTimeImmutable((string) $value))->format('d.m.Y H:i:s');
    } catch (Throwable) {
        return (string) $value;
    }
};
$modeLabel = (string) (Loc::getMessage('GCS_MODE_' . $mode . '_LABEL') ?: $mode);
$modeDescription = (string) (Loc::getMessage('GCS_MODE_' . $mode . '_DESCRIPTION') ?: '');
$modeClass = match ($mode) {'PROTECT' => 'protect', 'DISABLED' => 'disabled', default => 'observe'};
$metricClasses = ['scans' => 'neutral', 'blocked' => 'danger', 'warnings' => 'warning', 'errors' => 'danger'];
?>
<style>
.gcs-dashboard{max-width:1240px;margin-bottom:48px;color:#263238;font-size:14px}.gcs-label-hint{display:inline-flex;align-items:center;gap:3px}.gcs-intro{margin:0 0 17px;color:#596a72;line-height:1.55}.gcs-mode{display:grid;grid-template-columns:auto 1fr auto;gap:16px;align-items:center;padding:20px;border:1px solid #c9d9e0;border-radius:11px;background:#fff;box-shadow:0 2px 7px rgba(0,0,0,.04)}.gcs-mode-icon{display:flex;align-items:center;justify-content:center;width:46px;height:46px;border-radius:50%;background:#e6f1f5;color:#315b72;font-size:24px;font-weight:bold}.gcs-mode.protect .gcs-mode-icon{background:#dff4e7;color:#14663b}.gcs-mode.disabled .gcs-mode-icon{background:#eceff1;color:#687780}.gcs-mode h2{margin:0 0 5px;font-size:21px}.gcs-mode p{margin:0;color:#5e6e75;line-height:1.45}.gcs-settings-link{display:inline-flex;align-items:center;min-height:36px;padding:0 13px;border:1px solid #a9bac2;border-radius:5px;color:#315b72;text-decoration:none;white-space:nowrap}.gcs-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin:16px 0}.gcs-kpi{padding:17px;border:1px solid #d9e1e5;border-radius:9px;background:#fff}.gcs-kpi-label{display:flex;align-items:center;gap:3px;color:#65757c;font-size:13px}.gcs-kpi-main{display:block;margin:8px 0 3px;font-size:29px;line-height:1;font-weight:bold}.gcs-kpi-week{color:#74838a;font-size:12px}.gcs-kpi.danger .gcs-kpi-main{color:#b92c22}.gcs-kpi.warning .gcs-kpi-main{color:#946800}.gcs-grid{display:grid;grid-template-columns:minmax(0,1.55fr) minmax(300px,.85fr);gap:16px}.gcs-panel{padding:19px;border:1px solid #d9e1e5;border-radius:10px;background:#fff}.gcs-panel h2{margin:0 0 5px;font-size:19px}.gcs-panel-intro{margin:0 0 14px;color:#687780;line-height:1.4}.gcs-table-wrap{overflow:auto}.gcs-table{width:100%;border-collapse:collapse}.gcs-table th,.gcs-table td{padding:11px 9px;border-bottom:1px solid #e5eaed;text-align:left;vertical-align:middle}.gcs-table th{color:#687780;font-size:12px;text-transform:uppercase;white-space:nowrap}.gcs-table tr:last-child td{border-bottom:0}.gcs-file{font-weight:600;overflow-wrap:anywhere}.gcs-muted{display:block;margin-top:3px;color:#7a888e;font-size:12px}.gcs-open{color:#315b72;text-decoration:none;font-weight:600;white-space:nowrap}.gcs-latest{padding:16px;border-radius:8px;background:#f3f7f8}.gcs-latest strong{display:block;margin-bottom:5px;font-size:16px;overflow-wrap:anywhere}.gcs-latest-meta{color:#65757c;line-height:1.45}.gcs-empty{padding:18px;border-radius:8px;background:#f6f8f9;color:#687780}.gcs-baselines{margin-top:16px}.gcs-baseline-state{display:inline-flex;padding:5px 8px;border-radius:999px;font-size:12px;font-weight:600}.gcs-baseline-state.ready{background:#dff4e7;color:#14663b}.gcs-baseline-state.not-ready{background:#fff0c7;color:#875d00}.gcs-source{max-width:320px;overflow-wrap:anywhere}.gcs-warning{margin:16px 0 0}@media(max-width:980px){.gcs-kpis{grid-template-columns:repeat(2,minmax(0,1fr))}.gcs-grid{grid-template-columns:1fr}}@media(max-width:600px){.gcs-mode{grid-template-columns:auto 1fr}.gcs-mode .gcs-settings-link{grid-column:1/-1}.gcs-kpis{grid-template-columns:1fr}}
</style>
<div class="gcs-dashboard">
    <p class="gcs-intro"><?= $escape(Loc::getMessage('GCS_DASHBOARD_INTRO')) ?></p>
    <section class="gcs-mode <?= $escape($modeClass) ?>">
        <div class="gcs-mode-icon" aria-hidden="true"><?= $mode === 'PROTECT' ? '✓' : ($mode === 'DISABLED' ? '–' : 'i') ?></div>
        <div><h2 class="gcs-label-hint"><?= $escape(Loc::getMessage('GCS_MODE')) ?>: <?= $escape($modeLabel) ?><?php ShowJSHint((string) Loc::getMessage('GCS_HINT_DASHBOARD_MODE')); ?></h2><p><?= $escape($modeDescription) ?></p></div>
        <a class="gcs-settings-link" href="settings.php?mid=gorshkov.catalogsentinel&amp;lang=<?= urlencode(LANGUAGE_ID) ?>"><?= $escape(Loc::getMessage('GCS_OPEN_SETTINGS')) ?></a>
    </section>
    <?php if ($mode === 'PROTECT' && $readyBaselines === []): ?><div class="adm-info-message adm-info-message-red gcs-warning"><?= $escape(Loc::getMessage('GCS_PROTECT_WITHOUT_BASELINE')) ?></div><?php endif; ?>

    <div class="gcs-kpis">
        <?php foreach (['scans', 'blocked', 'warnings', 'errors'] as $metric): ?>
            <div class="gcs-kpi <?= $escape($metricClasses[$metric]) ?>"><span class="gcs-kpi-label"><?= $escape(Loc::getMessage('GCS_METRIC_' . strtoupper($metric))) ?> · <?= $escape(Loc::getMessage('GCS_LAST_24_HOURS')) ?><?php ShowJSHint((string) Loc::getMessage('GCS_HINT_METRIC_' . strtoupper($metric))); ?></span><strong class="gcs-kpi-main"><?= (int) $day[$metric] ?></strong><span class="gcs-kpi-week"><?= $escape(Loc::getMessage('GCS_LAST_7_DAYS_VALUE', ['#COUNT#' => (string) $week[$metric]])) ?></span></div>
        <?php endforeach; ?>
    </div>

    <div class="gcs-grid">
        <section class="gcs-panel">
            <h2 class="gcs-label-hint"><?= $escape(Loc::getMessage('GCS_RECENT_BLOCKS')) ?><?php ShowJSHint((string) Loc::getMessage('GCS_HINT_RECENT_BLOCKS')); ?></h2>
            <p class="gcs-panel-intro"><?= $escape(Loc::getMessage('GCS_RECENT_BLOCKS_HINT')) ?></p>
            <?php if ($blocks === []): ?><div class="gcs-empty"><?= $escape(Loc::getMessage('GCS_NO_BLOCKS')) ?></div><?php else: ?>
                <div class="gcs-table-wrap"><table class="gcs-table"><thead><tr><th><?= $escape(Loc::getMessage('GCS_DATE')) ?></th><th><?= $escape(Loc::getMessage('GCS_FILE')) ?></th><th><?= $escape(Loc::getMessage('GCS_SOURCE')) ?></th><th></th></tr></thead><tbody>
                <?php foreach ($blocks as $row): ?><tr><td><?= $escape($formatDate($row['CREATED_AT'])) ?></td><td class="gcs-file"><?= $escape($row['FILE_NAME']) ?><span class="gcs-muted">#<?= (int) $row['ID'] ?></span></td><td><?= $escape($row['SOURCE_LABEL']) ?></td><td><a class="gcs-open" href="gorshkov_catalogsentinel_scan.php?id=<?= (int) $row['ID'] ?>&amp;lang=<?= urlencode(LANGUAGE_ID) ?>"><?= $escape(Loc::getMessage('GCS_OPEN')) ?></a></td></tr><?php endforeach; ?>
                </tbody></table></div>
            <?php endif; ?>
        </section>
        <section class="gcs-panel">
            <h2 class="gcs-label-hint"><?= $escape(Loc::getMessage('GCS_LATEST_IMPORTED')) ?><?php ShowJSHint((string) Loc::getMessage('GCS_HINT_LATEST_IMPORTED')); ?></h2>
            <p class="gcs-panel-intro"><?= $escape(Loc::getMessage('GCS_LATEST_IMPORTED_HINT')) ?></p>
            <?php if ($latestImported === null): ?><div class="gcs-empty"><?= $escape(Loc::getMessage('GCS_NONE')) ?></div><?php else: ?>
                <div class="gcs-latest"><strong><?= $escape($latestImported['FILE_NAME']) ?></strong><div class="gcs-latest-meta"><?= $escape($latestImported['SOURCE_LABEL']) ?><br><?= $escape($formatDate($latestImported['IMPORTED_AT'])) ?></div><p><a class="gcs-open" href="gorshkov_catalogsentinel_scan.php?id=<?= (int) $latestImported['ID'] ?>&amp;lang=<?= urlencode(LANGUAGE_ID) ?>"><?= $escape(Loc::getMessage('GCS_OPEN_RESULT')) ?></a></p></div>
            <?php endif; ?>
        </section>
    </div>

    <section class="gcs-panel gcs-baselines">
        <h2 class="gcs-label-hint"><?= $escape(Loc::getMessage('GCS_BASELINE_STATUS')) ?><?php ShowJSHint((string) Loc::getMessage('GCS_HINT_BASELINE_STATUS')); ?></h2>
        <p class="gcs-panel-intro"><?= $escape(Loc::getMessage('GCS_BASELINE_STATUS_HINT', ['#MINIMUM#' => (string) $minimumSamples, '#WINDOW#' => (string) $window])) ?></p>
        <?php if ($baselines === []): ?><div class="gcs-empty"><?= $escape(Loc::getMessage('GCS_NO_BASELINES')) ?></div><?php else: ?>
            <div class="gcs-table-wrap"><table class="gcs-table"><thead><tr><th><?= $escape(Loc::getMessage('GCS_SOURCE')) ?></th><th><?= $escape(Loc::getMessage('GCS_KIND')) ?></th><th><?= $escape(Loc::getMessage('GCS_BASELINE_READINESS')) ?></th><th><?= $escape(Loc::getMessage('GCS_NORMAL_SAMPLES')) ?></th></tr></thead><tbody>
            <?php foreach ($baselines as $baseline): ?>
                <?php $ready = $baseline['STATUS'] === 'READY'; $sourceKey = (string) $baseline['SOURCE_KEY']; ?>
                <tr><td class="gcs-source"><?= $escape($sources[$sourceKey] ?? Loc::getMessage('GCS_UNKNOWN_SOURCE')) ?></td><td><?= $escape(Loc::getMessage('GCS_KIND_' . $baseline['DOCUMENT_KIND']) ?: $baseline['DOCUMENT_KIND']) ?></td><td><span class="gcs-baseline-state <?= $ready ? 'ready' : 'not-ready' ?>"><?= $escape(Loc::getMessage($ready ? 'GCS_BASELINE_READY' : 'GCS_BASELINE_NOT_READY')) ?></span></td><td><?= (int) $baseline['COUNT'] ?></td></tr>
            <?php endforeach; ?>
            </tbody></table></div>
        <?php endif; ?>
    </section>
    <div class="adm-info-message"><?= $escape(Loc::getMessage('GCS_FILE_SCOPE_NOTICE')) ?></div>
</div>
<?php require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php'; ?>
