<?php

declare(strict_types=1);

use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Gorshkov\CatalogSentinel\Application\Baseline\ResetBaseline;
use Gorshkov\CatalogSentinel\Bitrix\Admin\AdminDataProvider;
use Gorshkov\CatalogSentinel\Bitrix\Admin\AdminGuard;
use Gorshkov\CatalogSentinel\Infrastructure\Persistence\BitrixBaselineRepository;

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php';
Loader::includeModule('gorshkov.catalogsentinel') || die();
Loc::loadMessages(__FILE__);
AdminGuard::requireRead();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset'])) {
    AdminGuard::requirePost();
    (new ResetBaseline(new BitrixBaselineRepository()))->execute(
        (string) $_POST['source_key'],
        (string) $_POST['document_kind'],
        (int) $_POST['schema_version'],
    );
}

$minimum = (int) Option::get(AdminGuard::MODULE_ID, 'baseline_min_samples', '3');
$window = (int) Option::get(AdminGuard::MODULE_ID, 'baseline_window', '5');
$provider = new AdminDataProvider();
$groups = $provider->baselines($minimum, $window);
$sources = $provider->sourceOptions();
$readyCount = count(array_filter($groups, static fn (array $group): bool => $group['STATUS'] === 'READY'));
$APPLICATION->SetTitle((string) Loc::getMessage('GCS_BASELINES_TITLE'));
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';

$escape = static fn (mixed $value): string => htmlspecialcharsbx((string) $value);
$formatDate = static function (mixed $value): string {
    if ($value instanceof DateTimeInterface) {
        return $value->format('d.m.Y H:i');
    }

    try {
        return (new DateTimeImmutable((string) $value))->format('d.m.Y H:i');
    } catch (Throwable) {
        return (string) $value;
    }
};
$kindLabel = static fn (string $value): string => (string) (Loc::getMessage('GCS_KIND_' . $value) ?: $value);
$json = static function (mixed $value): string {
    $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    return is_string($encoded) ? $encoded : '{}';
};
?>
<style>
.gcs-baselines-page{max-width:1180px;margin-bottom:48px;color:#263238;font-size:14px}.gcs-baselines-intro{max-width:900px;margin:0 0 18px;color:#596a72;line-height:1.55}.gcs-baselines-summary{display:grid;grid-template-columns:repeat(2,minmax(220px,1fr));gap:14px;max-width:650px;margin-bottom:18px}.gcs-baseline-kpi{padding:17px 19px;border:1px solid #d7e0e4;border-radius:10px;background:#fff}.gcs-baseline-kpi span{display:block;color:#687780}.gcs-baseline-kpi strong{display:block;margin-top:6px;font-size:27px}.gcs-baseline-list{display:grid;gap:14px}.gcs-baseline-card{padding:20px;border:1px solid #d7e0e4;border-radius:11px;background:#fff;box-shadow:0 2px 7px rgba(0,0,0,.04)}.gcs-baseline-head{display:flex;justify-content:space-between;gap:16px;align-items:flex-start}.gcs-baseline-head h2{margin:0 0 5px;font-size:19px}.gcs-baseline-kind{color:#687780}.gcs-state{display:inline-flex;padding:6px 9px;border-radius:999px;font-size:12px;font-weight:600;white-space:nowrap}.gcs-state.ready{background:#dff4e7;color:#14663b}.gcs-state.not-ready{background:#fff0c7;color:#875d00}.gcs-baseline-grid{display:grid;grid-template-columns:1.2fr 1fr 1fr;gap:14px;margin-top:18px}.gcs-baseline-field{padding:13px 14px;border-radius:8px;background:#f5f8f9}.gcs-baseline-label{display:block;margin-bottom:6px;color:#687780;font-size:12px;text-transform:uppercase;letter-spacing:.03em}.gcs-baseline-value{font-size:16px;font-weight:600}.gcs-progress{height:8px;margin-top:9px;overflow:hidden;border-radius:8px;background:#dfe7ea}.gcs-progress span{display:block;height:100%;border-radius:8px;background:#3b7b97}.gcs-scan-links{margin-top:15px;color:#687780}.gcs-scan-links a{display:inline-flex;margin:5px 5px 0 0;padding:5px 8px;border:1px solid #cbd7dc;border-radius:5px;color:#315b72;text-decoration:none}.gcs-card-footer{display:flex;justify-content:space-between;gap:12px;align-items:center;margin-top:16px;padding-top:15px;border-top:1px solid #e4eaed}.gcs-technical summary{cursor:pointer;color:#315b72;font-weight:600}.gcs-technical pre{max-width:760px;padding:12px;overflow:auto;border-radius:7px;background:#f3f6f7;font:12px/1.45 Consolas,monospace;white-space:pre-wrap}.gcs-reset{padding:8px 12px;border:1px solid #c56f68;border-radius:5px;background:#fff;color:#a22d24;cursor:pointer}.gcs-empty{padding:26px;border:1px solid #d7e0e4;border-radius:10px;background:#fff;color:#687780}.gcs-label-inline{display:inline-flex;align-items:center;gap:3px}@media(max-width:760px){.gcs-baselines-summary,.gcs-baseline-grid{grid-template-columns:1fr}.gcs-baseline-head,.gcs-card-footer{display:block}.gcs-state{margin-top:10px}.gcs-reset{margin-top:14px}}
</style>
<div class="gcs-baselines-page">
    <p class="gcs-baselines-intro"><?= $escape(Loc::getMessage('GCS_BASELINES_INTRO', ['#MINIMUM#' => (string) $minimum, '#WINDOW#' => (string) $window])) ?></p>
    <div class="gcs-baselines-summary">
        <div class="gcs-baseline-kpi"><span class="gcs-label-inline"><?= $escape(Loc::getMessage('GCS_BASELINES_TOTAL')) ?><?php ShowJSHint((string) Loc::getMessage('GCS_HINT_BASELINES_TOTAL')); ?></span><strong><?= count($groups) ?></strong></div>
        <div class="gcs-baseline-kpi"><span class="gcs-label-inline"><?= $escape(Loc::getMessage('GCS_BASELINES_READY')) ?><?php ShowJSHint((string) Loc::getMessage('GCS_HINT_BASELINES_READY')); ?></span><strong><?= $readyCount ?></strong></div>
    </div>

    <?php if ($groups === []): ?>
        <div class="gcs-empty"><?= $escape(Loc::getMessage('GCS_BASELINES_EMPTY')) ?></div>
    <?php else: ?>
        <div class="gcs-baseline-list">
            <?php foreach ($groups as $group): ?>
                <?php
                $sourceKey = (string) $group['SOURCE_KEY'];
                $sourceLabel = trim((string) ($sources[$sourceKey] ?? ''));
                $sourceLabel = $sourceLabel !== '' ? $sourceLabel : (string) Loc::getMessage('GCS_UNKNOWN_SOURCE');
                $ready = $group['STATUS'] === 'READY';
                $count = (int) $group['COUNT'];
                $progress = min(100, (int) round(($count / max(1, $minimum)) * 100));
                ?>
                <section class="gcs-baseline-card">
                    <div class="gcs-baseline-head">
                        <div>
                            <h2 class="gcs-label-inline"><?= $escape($sourceLabel) ?><?php ShowJSHint((string) Loc::getMessage('GCS_HINT_BASELINE_SOURCE')); ?></h2>
                            <div class="gcs-baseline-kind gcs-label-inline"><?= $escape($kindLabel((string) $group['DOCUMENT_KIND'])) ?><?php ShowJSHint((string) Loc::getMessage('GCS_HINT_BASELINE_KIND')); ?></div>
                        </div>
                        <span class="gcs-state <?= $ready ? 'ready' : 'not-ready' ?>"><?= $escape(Loc::getMessage($ready ? 'GCS_BASELINE_READY' : 'GCS_BASELINE_NOT_READY')) ?></span>
                    </div>
                    <div class="gcs-baseline-grid">
                        <div class="gcs-baseline-field">
                            <span class="gcs-baseline-label gcs-label-inline"><?= $escape(Loc::getMessage('GCS_NORMAL_SAMPLES')) ?><?php ShowJSHint((string) Loc::getMessage('GCS_HINT_BASELINE_SAMPLES')); ?></span>
                            <span class="gcs-baseline-value"><?= $count ?> / <?= $minimum ?></span>
                            <div class="gcs-progress" aria-hidden="true"><span style="width:<?= $progress ?>%"></span></div>
                        </div>
                        <div class="gcs-baseline-field"><span class="gcs-baseline-label gcs-label-inline"><?= $escape(Loc::getMessage('GCS_BASELINE_FIRST')) ?><?php ShowJSHint((string) Loc::getMessage('GCS_HINT_BASELINE_FIRST')); ?></span><span class="gcs-baseline-value"><?= $escape($formatDate($group['FIRST_AT'])) ?></span></div>
                        <div class="gcs-baseline-field"><span class="gcs-baseline-label gcs-label-inline"><?= $escape(Loc::getMessage('GCS_BASELINE_LAST')) ?><?php ShowJSHint((string) Loc::getMessage('GCS_HINT_BASELINE_LAST')); ?></span><span class="gcs-baseline-value"><?= $escape($formatDate($group['LAST_AT'])) ?></span></div>
                    </div>
                    <div class="gcs-scan-links"><span class="gcs-label-inline"><?= $escape(Loc::getMessage('GCS_BASELINE_SCANS')) ?><?php ShowJSHint((string) Loc::getMessage('GCS_HINT_BASELINE_SCANS')); ?></span><br><?php foreach ($group['SCAN_IDS'] as $scanId): ?><a href="gorshkov_catalogsentinel_scan.php?id=<?= (int) $scanId ?>&amp;lang=<?= urlencode(LANGUAGE_ID) ?>">#<?= (int) $scanId ?></a><?php endforeach; ?></div>
                    <div class="gcs-card-footer">
                        <details class="gcs-technical">
                            <summary class="gcs-label-inline"><?= $escape(Loc::getMessage('GCS_BASELINE_TECHNICAL')) ?><?php ShowJSHint((string) Loc::getMessage('GCS_HINT_BASELINE_TECHNICAL')); ?></summary>
                            <p><strong><?= $escape(Loc::getMessage('GCS_BASELINE_SCHEMA')) ?>:</strong> <?= (int) $group['ANALYZER_SCHEMA_VERSION'] ?></p>
                            <p><strong><?= $escape(Loc::getMessage('GCS_BASELINE_MEDIANS')) ?>:</strong></p>
                            <pre><?= $escape($json($group['MEDIANS'])) ?></pre>
                        </details>
                        <?php if (AdminGuard::right() >= 'W'): ?>
                            <form method="post" onsubmit="return confirm('<?= CUtil::JSEscape((string) Loc::getMessage('GCS_RESET_CONFIRM')) ?>')">
                                <?= bitrix_sessid_post() ?>
                                <input type="hidden" name="source_key" value="<?= $escape($sourceKey) ?>">
                                <input type="hidden" name="document_kind" value="<?= $escape($group['DOCUMENT_KIND']) ?>">
                                <input type="hidden" name="schema_version" value="<?= (int) $group['ANALYZER_SCHEMA_VERSION'] ?>">
                                <button class="gcs-reset" name="reset" value="Y" title="<?= $escape(Loc::getMessage('GCS_HINT_BASELINE_RESET')) ?>"><?= $escape(Loc::getMessage('GCS_RESET')) ?></button>
                            </form>
                        <?php endif; ?>
                    </div>
                </section>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php'; ?>
