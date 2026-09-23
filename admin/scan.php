<?php

declare(strict_types=1);

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Gorshkov\CatalogSentinel\Application\Baseline\AcceptBaselineSample;
use Gorshkov\CatalogSentinel\Bitrix\Admin\AdminDataProvider;
use Gorshkov\CatalogSentinel\Bitrix\Admin\AdminGuard;
use Gorshkov\CatalogSentinel\Infrastructure\Persistence\BitrixBaselineRepository;
use Gorshkov\CatalogSentinel\Infrastructure\Persistence\BitrixScanRepository;

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php';
Loader::includeModule('gorshkov.catalogsentinel') || die();
Loc::loadMessages(__FILE__);
AdminGuard::requireRead();

$id = max(0, (int) ($_REQUEST['id'] ?? 0));
$provider = new AdminDataProvider();
$id = $id > 0 ? $id : ($provider->latestScanId() ?? 0);
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accept_baseline'])) {
    AdminGuard::requirePost();
    $accepted = (new AcceptBaselineSample(new BitrixScanRepository(), new BitrixBaselineRepository()))
        ->execute($id, (int) $USER->GetID());
    $message = $accepted ? 'accepted' : 'not_accepted';
}

$scan = $provider->scan($id);
if ($scan === null) {
    CAdminMessage::ShowMessage((string) Loc::getMessage('GCS_NOT_FOUND'));
}

$APPLICATION->SetTitle((string) Loc::getMessage('GCS_SCAN_TITLE') . ' #' . $id);
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';

if ($scan === null) {
    require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
    return;
}

$escape = static fn (mixed $value): string => htmlspecialcharsbx((string) $value);
$formatNumber = static function (float|int $value, int $precision = 0): string {
    $formatted = number_format((float) $value, $precision, ',', ' ');
    return $precision > 0 ? (string) preg_replace('/,0+$/', '', $formatted) : $formatted;
};
$formatPercent = static function (mixed $value) use ($formatNumber): string {
    return is_numeric($value) ? $formatNumber((float) $value * 100, 1) . '%' : '—';
};
$formatBytes = static function (int $bytes) use ($formatNumber): string {
    if ($bytes >= 1048576) {
        return $formatNumber($bytes / 1048576, 1) . ' MB';
    }
    if ($bytes >= 1024) {
        return $formatNumber($bytes / 1024, 1) . ' KB';
    }
    return $formatNumber($bytes) . ' B';
};
$formatDate = static function (string $value): string {
    try {
        return (new DateTimeImmutable($value))->format('d.m.Y H:i:s');
    } catch (Throwable) {
        return $value;
    }
};
$json = static function (array $value): string {
    return (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
};

$ruleLabels = [
    'xml.well_formed' => (string) Loc::getMessage('GCS_RULE_XML_WELL_FORMED'),
    'document.supported' => (string) Loc::getMessage('GCS_RULE_DOCUMENT_SUPPORTED'),
    'document.item_count_drop' => (string) Loc::getMessage('GCS_RULE_ITEM_COUNT_DROP'),
    'stock.zero_spike' => (string) Loc::getMessage('GCS_RULE_STOCK_ZERO_SPIKE'),
    'price.zero_spike' => (string) Loc::getMessage('GCS_RULE_PRICE_ZERO_SPIKE'),
    'property.empty_spike' => (string) Loc::getMessage('GCS_RULE_PROPERTY_EMPTY_SPIKE'),
    'identifier.missing_ratio' => (string) Loc::getMessage('GCS_RULE_IDENTIFIER_MISSING'),
    'file.size_drop' => (string) Loc::getMessage('GCS_RULE_FILE_SIZE_DROP'),
];
$outcomeLabels = [
    'PASS' => (string) Loc::getMessage('GCS_OUTCOME_PASS'),
    'WARN' => (string) Loc::getMessage('GCS_OUTCOME_WARN'),
    'BLOCK' => (string) Loc::getMessage('GCS_OUTCOME_BLOCK'),
    'ERROR' => (string) Loc::getMessage('GCS_OUTCOME_ERROR'),
    'NOT_APPLICABLE' => (string) Loc::getMessage('GCS_OUTCOME_NOT_APPLICABLE'),
];
$warningLabels = [
    'stock_profile.warehouses_preferred' => (string) Loc::getMessage('GCS_WARNING_STOCK_PROFILE'),
];
$sampleCategories = [
    'stock.zero_spike' => 'stock_zero_or_negative',
    'price.zero_spike' => 'price_all_zero',
    'identifier.missing_ratio' => 'missing_id',
];

$decision = $scan->decision ?? 'ERROR';
$decisionClass = match ($decision) {
    'BLOCK' => 'block',
    'WARN' => 'warn',
    'ERROR' => 'error',
    default => 'pass',
};
$primaryRule = null;
foreach ($scan->ruleResults as $rule) {
    if ($rule->outcome === $decision) {
        $primaryRule = $rule;
        break;
    }
}
if ($primaryRule === null) {
    foreach ($scan->ruleResults as $rule) {
        if (in_array($rule->outcome, ['BLOCK', 'WARN', 'ERROR'], true)) {
            $primaryRule = $rule;
            break;
        }
    }
}

$objectsTotal = (int) ($scan->metrics['document.objects_total'] ?? 0);
$affected = $primaryRule !== null && isset($primaryRule->actual['affected'])
    ? (int) $primaryRule->actual['affected']
    : 0;
$currentRatio = $primaryRule?->actual['ratio'] ?? null;
$baselineRatio = $primaryRule?->baseline['median_ratio'] ?? null;
$thresholdRatio = $primaryRule?->thresholds['min_current_ratio'] ?? null;
$primaryRuleCode = $primaryRule?->ruleCode ?? '';
$primaryRuleLabel = $ruleLabels[$primaryRuleCode] ?? $primaryRuleCode;
$sampleCategory = $sampleCategories[$primaryRuleCode] ?? '';
$productIds = $sampleCategory !== '' ? ($scan->samples[$sampleCategory] ?? []) : [];
$productLabels = $sampleCategory !== '' ? ($scan->samples[$sampleCategory . '.labels'] ?? []) : [];
$productRows = [];
foreach ($productIds as $index => $productId) {
    $productRows[] = [
        'id' => $productId,
        'name' => $productLabels[$index] ?? $productId,
        'has_name' => isset($productLabels[$index]) && $productLabels[$index] !== $productId,
    ];
}
$hasFullReport = array_filter(
    $scan->ruleResults,
    static fn ($rule): bool => in_array($rule->ruleCode, ['stock.zero_spike', 'price.zero_spike', 'property.empty_spike', 'identifier.missing_ratio'], true)
        && in_array($rule->outcome, ['WARN', 'BLOCK'], true),
) !== [];

$heroTitle = match ($decision) {
    'BLOCK' => $scan->mode === 'PROTECT'
        ? (string) Loc::getMessage('GCS_HERO_BLOCK_PROTECT')
        : (string) Loc::getMessage('GCS_HERO_BLOCK_OBSERVE'),
    'WARN' => (string) Loc::getMessage('GCS_HERO_WARN'),
    'ERROR' => (string) Loc::getMessage('GCS_HERO_ERROR'),
    default => (string) Loc::getMessage('GCS_HERO_PASS'),
};
if ($primaryRuleCode === 'stock.zero_spike') {
    $heroText = (string) Loc::getMessage('GCS_SUMMARY_STOCK_ZERO', [
        '#AFFECTED#' => $formatNumber($affected),
        '#TOTAL#' => $formatNumber($objectsTotal),
        '#CURRENT#' => $formatPercent($currentRatio),
        '#BASELINE#' => $formatPercent($baselineRatio),
    ]);
} elseif ($primaryRuleCode === 'price.zero_spike') {
    $heroText = (string) Loc::getMessage('GCS_SUMMARY_PRICE_ZERO', [
        '#AFFECTED#' => $formatNumber($affected),
        '#TOTAL#' => $formatNumber($objectsTotal),
        '#CURRENT#' => $formatPercent($currentRatio),
        '#BASELINE#' => $formatPercent($baselineRatio),
    ]);
} else {
    $heroText = (string) Loc::getMessage('GCS_SUMMARY_DEFAULT', [
        '#RULE#' => $primaryRuleLabel !== '' ? $primaryRuleLabel : (string) Loc::getMessage('GCS_NONE'),
    ]);
}

$modeLabel = $scan->mode === 'PROTECT'
    ? (string) Loc::getMessage('GCS_MODE_PROTECT_HISTORICAL')
    : (string) Loc::getMessage('GCS_MODE_OBSERVE_HISTORICAL');
$decisionLabel = $outcomeLabels[$decision] ?? $decision;
$generalData = [
    'ID' => $scan->id,
    'STATUS' => $scan->status,
    'DECISION' => $decision,
    'MODE' => $scan->mode,
    'DOCUMENT_KIND' => $scan->documentKind,
    'FILE_NAME' => $scan->file->fileName,
    'FILE_SIZE' => $scan->file->size,
    'SOURCE_LABEL' => $scan->sourceLabel,
    'DURATION_MS' => $scan->durationMs,
    'MEMORY_DELTA_BYTES' => $scan->memoryDeltaBytes,
];
?>
<style>
.gcs-page{max-width:1280px;margin:0 auto 48px;color:#263238;font-family:Arial,sans-serif}.gcs-label-hint{display:inline-flex!important;align-items:center;gap:3px}.gcs-nav{display:flex;gap:8px;flex-wrap:wrap;margin:0 0 18px}.gcs-nav a{padding:8px 12px;border:1px solid #cbd5dc;border-radius:6px;background:#fff;color:#315b72;text-decoration:none}.gcs-hero{display:grid;grid-template-columns:54px 1fr auto;gap:18px;align-items:start;padding:24px;border:1px solid;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,.05)}.gcs-hero.block,.gcs-hero.error{background:#fff4f3;border-color:#f2aaa5}.gcs-hero.warn{background:#fff9e8;border-color:#ead28a}.gcs-hero.pass{background:#f0faf4;border-color:#9dd4b0}.gcs-state-icon{width:48px;height:48px;display:flex;align-items:center;justify-content:center;border-radius:50%;font-size:27px;font-weight:bold;color:#fff}.block .gcs-state-icon,.error .gcs-state-icon{background:#d92d20}.warn .gcs-state-icon{background:#c68700}.pass .gcs-state-icon{background:#16834b}.gcs-hero h2{margin:0 0 8px;font-size:26px}.gcs-hero p{max-width:800px;margin:0;color:#4d5b62;font-size:15px;line-height:1.55}.gcs-badge{display:inline-flex;padding:6px 10px;border-radius:999px;font-size:12px;font-weight:bold;white-space:nowrap}.gcs-badge.block,.gcs-badge.error{background:#d92d20;color:#fff}.gcs-badge.warn{background:#c68700;color:#fff}.gcs-badge.pass{background:#16834b;color:#fff}.gcs-context{margin-top:10px;color:#687780;font-size:12px}.gcs-cards{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin:16px 0}.gcs-card,.gcs-section{padding:18px;background:#fff;border:1px solid #d9e0e4;border-radius:10px;min-width:0}.gcs-card-label{display:flex;align-items:center;gap:3px;margin-bottom:9px;color:#687780;font-size:12px;text-transform:uppercase;letter-spacing:.04em}.gcs-card-value{display:block;font-size:25px;font-weight:bold;color:#1e3038;overflow-wrap:anywhere}.gcs-card-note{display:block;margin-top:6px;color:#687780;font-size:12px}.gcs-section{margin-top:16px;padding:20px}.gcs-section h2{margin:0 0 14px;font-size:20px}.gcs-reason{display:grid;grid-template-columns:minmax(220px,1.3fr) repeat(3,minmax(150px,.7fr));gap:12px}.gcs-reason-main,.gcs-fact{padding:15px;border-radius:8px;background:#f5f8fa}.gcs-reason-code{display:block;margin-top:5px;color:#687780;font-family:monospace}.gcs-fact strong{display:block;margin-top:6px;font-size:20px}.gcs-actions{margin:0;padding-left:20px;line-height:1.7}.gcs-products-meta{margin:-7px 0 14px;color:#687780}.gcs-products{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px;max-height:390px;margin:0;padding:0;overflow:auto;list-style:none}.gcs-product{padding:11px 13px;border:1px solid #e1e7ea;border-radius:7px;background:#fafcfd}.gcs-product-name{display:block;font-weight:bold;overflow-wrap:anywhere}.gcs-product-id{display:block;margin-top:4px;color:#6d7b82;font:12px Consolas,monospace;overflow-wrap:anywhere}.gcs-notice{margin-top:16px;padding:14px 16px;border-left:4px solid #7da9bf;background:#edf6fa;color:#40545e}.gcs-warning{border-left-color:#c68700;background:#fff9e8}.gcs-buttons{display:flex;gap:8px;flex-wrap:wrap;margin-top:16px}.gcs-button{display:inline-block;padding:9px 14px;border:1px solid #9fb2bc;border-radius:6px;background:#fff;color:#315b72;text-decoration:none}.gcs-button.primary{border-color:#315b72;background:#315b72;color:#fff}.gcs-details{margin-top:16px;border:1px solid #d9e0e4;border-radius:10px;background:#fff}.gcs-details>summary{cursor:pointer;padding:17px 20px;font-size:16px;font-weight:bold;list-style:none}.gcs-details>summary:after{content:'+';float:right;font-size:20px}.gcs-details[open]>summary:after{content:'−'}.gcs-details-body{padding:0 20px 20px}.gcs-table-wrap{overflow-x:auto}.gcs-table{width:100%;border-collapse:collapse}.gcs-table th,.gcs-table td{padding:10px;border-bottom:1px solid #e4eaed;text-align:left;vertical-align:top}.gcs-table th{color:#5f7078;font-size:12px;text-transform:uppercase}.gcs-rule-details summary{cursor:pointer;color:#315b72}.gcs-code{max-width:520px;margin:8px 0 0;padding:10px;overflow:auto;border-radius:6px;background:#f3f6f7;font:12px/1.45 Consolas,monospace;white-space:pre-wrap;overflow-wrap:anywhere}.gcs-samples{overflow-wrap:anywhere;line-height:1.5}.gcs-inline-form{display:inline-block;margin:0}.gcs-inline-form button{padding:9px 14px;border:0;border-radius:6px;background:#315b72;color:#fff;cursor:pointer}@media(max-width:1000px){.gcs-cards{grid-template-columns:repeat(2,minmax(0,1fr))}.gcs-reason{grid-template-columns:repeat(2,minmax(0,1fr))}.gcs-reason-main{grid-column:1/-1}}@media(max-width:650px){.gcs-hero{grid-template-columns:44px 1fr}.gcs-hero>.gcs-badge{grid-column:1/-1}.gcs-cards,.gcs-reason,.gcs-products{grid-template-columns:1fr}}
</style>

<div class="gcs-page">
    <nav class="gcs-nav" aria-label="Catalog Sentinel">
        <a href="gorshkov_catalogsentinel_dashboard.php?lang=<?= urlencode(LANGUAGE_ID) ?>"><?= $escape(Loc::getMessage('GCS_NAV_OVERVIEW')) ?></a>
        <a href="gorshkov_catalogsentinel_scans.php?lang=<?= urlencode(LANGUAGE_ID) ?>"><?= $escape(Loc::getMessage('GCS_NAV_SCANS')) ?></a>
        <a href="gorshkov_catalogsentinel_scan.php?lang=<?= urlencode(LANGUAGE_ID) ?>"><?= $escape(Loc::getMessage('GCS_NAV_LATEST')) ?></a>
        <a href="gorshkov_catalogsentinel_baselines.php?lang=<?= urlencode(LANGUAGE_ID) ?>"><?= $escape(Loc::getMessage('GCS_NAV_BASELINES')) ?></a>
        <a href="gorshkov_catalogsentinel_dry_run.php?lang=<?= urlencode(LANGUAGE_ID) ?>"><?= $escape(Loc::getMessage('GCS_NAV_DRY_RUN')) ?></a>
        <a href="settings.php?mid=gorshkov.catalogsentinel&amp;lang=<?= urlencode(LANGUAGE_ID) ?>"><?= $escape(Loc::getMessage('GCS_NAV_SETTINGS')) ?></a>
    </nav>

    <?php if ($message !== ''): ?><div class="adm-info-message"><?= $escape($message) ?></div><?php endif; ?>

    <section class="gcs-hero <?= $escape($decisionClass) ?>">
        <div class="gcs-state-icon" aria-hidden="true"><?= $decision === 'PASS' ? '✓' : '!' ?></div>
        <div>
            <h2><?= $escape($heroTitle) ?></h2>
            <p><?= $escape($heroText) ?></p>
            <div class="gcs-context"><?= $escape(Loc::getMessage('GCS_SCAN_CONTEXT', [
                '#ID#' => (string) $id,
                '#DATE#' => $formatDate($scan->createdAt),
            ])) ?> · <?= $escape($modeLabel) ?></div>
        </div>
        <span class="gcs-badge <?= $escape($decisionClass) ?>"><?= $escape($decisionLabel) ?></span>
    </section>

    <div class="gcs-cards">
        <div class="gcs-card"><span class="gcs-card-label"><?= $escape(Loc::getMessage('GCS_CURRENT_VALUE')) ?><?php ShowJSHint((string) Loc::getMessage('GCS_HINT_CURRENT_VALUE')); ?></span><span class="gcs-card-value"><?= $escape($formatPercent($currentRatio)) ?></span><span class="gcs-card-note"><?= $escape($primaryRuleLabel) ?></span></div>
        <div class="gcs-card"><span class="gcs-card-label"><?= $escape(Loc::getMessage('GCS_BASELINE_VALUE')) ?><?php ShowJSHint((string) Loc::getMessage('GCS_HINT_BASELINE_VALUE')); ?></span><span class="gcs-card-value"><?= $escape($formatPercent($baselineRatio)) ?></span><span class="gcs-card-note"><?= $escape(Loc::getMessage('GCS_BASELINE_SAMPLES', ['#COUNT#' => (string) ($primaryRule?->baseline['sample_count'] ?? 0)])) ?></span></div>
        <div class="gcs-card"><span class="gcs-card-label"><?= $escape(Loc::getMessage('GCS_AFFECTED_OBJECTS')) ?><?php ShowJSHint((string) Loc::getMessage('GCS_HINT_AFFECTED_OBJECTS')); ?></span><span class="gcs-card-value"><?= $escape($formatNumber($affected)) ?></span><span class="gcs-card-note"><?= $escape(Loc::getMessage('GCS_OF_OBJECTS', ['#TOTAL#' => $formatNumber($objectsTotal)])) ?></span></div>
        <div class="gcs-card"><span class="gcs-card-label"><?= $escape(Loc::getMessage('GCS_FILE_CARD')) ?><?php ShowJSHint((string) Loc::getMessage('GCS_HINT_SCAN_FILE')); ?></span><span class="gcs-card-value"><?= $escape($scan->file->fileName) ?></span><span class="gcs-card-note"><?= $escape($formatBytes($scan->file->size)) ?> · <?= $escape($scan->documentKind) ?> · <?= $escape((string) $scan->durationMs) ?> ms</span></div>
    </div>

    <?php if ($primaryRule !== null): ?>
        <section class="gcs-section">
            <h2 class="gcs-label-hint"><?= $escape(Loc::getMessage('GCS_PRIMARY_REASON')) ?><?php ShowJSHint((string) Loc::getMessage('GCS_HINT_PRIMARY_REASON')); ?></h2>
            <div class="gcs-reason">
                <div class="gcs-reason-main"><strong><?= $escape($primaryRuleLabel) ?></strong><span class="gcs-reason-code"><?= $escape($primaryRule->ruleCode) ?></span></div>
                <div class="gcs-fact"><?= $escape(Loc::getMessage('GCS_CURRENT_VALUE')) ?><strong><?= $escape($formatPercent($currentRatio)) ?></strong></div>
                <div class="gcs-fact"><?= $escape(Loc::getMessage('GCS_BASELINE_VALUE')) ?><strong><?= $escape($formatPercent($baselineRatio)) ?></strong></div>
                <div class="gcs-fact"><?= $escape(Loc::getMessage('GCS_TRIGGER_THRESHOLD')) ?><strong><?= $escape($formatPercent($thresholdRatio)) ?></strong></div>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($productRows !== []): ?>
        <section class="gcs-section">
            <h2 class="gcs-label-hint"><?= $escape(Loc::getMessage('GCS_AFFECTED_PRODUCTS')) ?><?php ShowJSHint((string) Loc::getMessage('GCS_HINT_AFFECTED_PRODUCTS')); ?></h2>
            <p class="gcs-products-meta"><?= $escape(Loc::getMessage('GCS_PRODUCTS_SHOWN', [
                '#SHOWN#' => $formatNumber(count($productRows)),
                '#TOTAL#' => $formatNumber($affected),
            ])) ?></p>
            <ul class="gcs-products">
                <?php foreach ($productRows as $product): ?>
                    <li class="gcs-product">
                        <span class="gcs-product-name"><?= $escape($product['name']) ?></span>
                        <span class="gcs-product-id"><?= $escape(Loc::getMessage('GCS_PRODUCT_ID')) ?>: <?= $escape($product['id']) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php if (!array_filter($productRows, static fn (array $row): bool => $row['has_name'])): ?>
                <div class="gcs-notice"><?= $escape(Loc::getMessage('GCS_LEGACY_NAMES_NOTICE')) ?></div>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <?php if ($decision === 'BLOCK'): ?>
        <section class="gcs-section"><h2 class="gcs-label-hint"><?= $escape(Loc::getMessage('GCS_NEXT_STEPS')) ?><?php ShowJSHint((string) Loc::getMessage('GCS_HINT_NEXT_STEPS')); ?></h2><ol class="gcs-actions"><li><?= $escape(Loc::getMessage('GCS_BLOCK_ACTION_CHECK')) ?></li><li><?= $escape(Loc::getMessage('GCS_BLOCK_ACTION_FIX')) ?></li><li><?= $escape(Loc::getMessage('GCS_BLOCK_ACTION_REPEAT')) ?></li></ol></section>
    <?php endif; ?>

    <?php if ($scan->warnings !== [] || $scan->errorCode !== null): ?>
        <div class="gcs-notice gcs-warning"><strong><?= $escape(Loc::getMessage('GCS_ANALYZER_NOTICE')) ?></strong><br><?php foreach ($scan->warnings as $warning): ?><?= $escape($warningLabels[$warning] ?? $warning) ?><br><?php endforeach; ?><?php if ($scan->errorCode !== null): ?><?= $escape($scan->errorCode) ?><?= $scan->errorMessage !== null ? ': ' . $escape($scan->errorMessage) : '' ?><?php endif; ?></div>
    <?php endif; ?>

    <div class="gcs-buttons">
        <a class="gcs-button primary" href="gorshkov_catalogsentinel_scans.php?lang=<?= urlencode(LANGUAGE_ID) ?>"><?= $escape(Loc::getMessage('GCS_BACK_TO_SCANS')) ?></a>
        <a class="gcs-button" title="<?= $escape(Loc::getMessage('GCS_HINT_EXPORT_JSON')) ?>" href="gorshkov_catalogsentinel_export.php?id=<?= $id ?>&amp;format=json&amp;lang=<?= urlencode(LANGUAGE_ID) ?>"><?= $escape(Loc::getMessage('GCS_EXPORT_JSON')) ?></a>
        <a class="gcs-button" title="<?= $escape(Loc::getMessage('GCS_HINT_EXPORT_CSV')) ?>" href="gorshkov_catalogsentinel_export.php?id=<?= $id ?>&amp;format=csv&amp;lang=<?= urlencode(LANGUAGE_ID) ?>"><?= $escape(Loc::getMessage('GCS_EXPORT_CSV')) ?></a>
        <?php if ($hasFullReport): ?><a class="gcs-button" title="<?= $escape(Loc::getMessage('GCS_HINT_FULL_REPORT_BUTTON')) ?>" target="_blank" rel="noopener" href="gorshkov_catalogsentinel_full_report.php?id=<?= $id ?>&amp;lang=<?= urlencode(LANGUAGE_ID) ?>"><?= $escape(Loc::getMessage('GCS_FULL_REPORT')) ?></a><?php endif; ?>
        <?php if ($scan->dryRun && $decision !== 'BLOCK' && AdminGuard::right() >= 'W'): ?><form class="gcs-inline-form" method="post"><?= bitrix_sessid_post() ?><input type="hidden" name="id" value="<?= $id ?>"><button name="accept_baseline" value="Y" title="<?= $escape(Loc::getMessage('GCS_HINT_ACCEPT_BASELINE')) ?>"><?= $escape(Loc::getMessage('GCS_ACCEPT_BASELINE')) ?></button></form><?php endif; ?>
    </div>

    <div class="gcs-notice"><?= $escape(Loc::getMessage('GCS_FILE_SCOPE_NOTICE')) ?></div>

    <details class="gcs-details">
        <summary><?= $escape(Loc::getMessage('GCS_TECHNICAL_DETAILS')) ?></summary>
        <div class="gcs-details-body">
            <h2><?= $escape(Loc::getMessage('GCS_GENERAL_DATA')) ?></h2>
            <div class="gcs-table-wrap"><table class="gcs-table"><tbody><?php foreach ($generalData as $label => $value): ?><tr><th><?= $escape($label) ?></th><td><?= $escape($value) ?></td></tr><?php endforeach; ?></tbody></table></div>
            <h2><?= $escape(Loc::getMessage('GCS_METRICS')) ?></h2>
            <div class="gcs-table-wrap"><table class="gcs-table"><thead><tr><th><?= $escape(Loc::getMessage('GCS_METRIC_NAME')) ?></th><th><?= $escape(Loc::getMessage('GCS_METRIC_VALUE')) ?></th></tr></thead><tbody><?php foreach ($scan->metrics as $name => $value): ?><tr><td><?= $escape($name) ?></td><td><?= $escape($value) ?></td></tr><?php endforeach; ?></tbody></table></div>
            <h2><?= $escape(Loc::getMessage('GCS_RULES')) ?></h2>
            <div class="gcs-table-wrap"><table class="gcs-table"><thead><tr><th><?= $escape(Loc::getMessage('GCS_RULE_NAME')) ?></th><th><?= $escape(Loc::getMessage('GCS_RESULT')) ?></th><th><?= $escape(Loc::getMessage('GCS_RULE_DATA')) ?></th></tr></thead><tbody>
            <?php foreach ($scan->ruleResults as $rule): ?><tr><td><strong><?= $escape($ruleLabels[$rule->ruleCode] ?? $rule->ruleCode) ?></strong><span class="gcs-reason-code"><?= $escape($rule->ruleCode) ?></span></td><td><?= $escape($outcomeLabels[$rule->outcome] ?? $rule->outcome) ?></td><td><details class="gcs-rule-details"><summary><?= $escape(Loc::getMessage('GCS_SHOW_VALUES')) ?></summary><strong>actual</strong><pre class="gcs-code"><?= $escape($json($rule->actual)) ?></pre><strong>baseline</strong><pre class="gcs-code"><?= $escape($json($rule->baseline)) ?></pre><strong>thresholds</strong><pre class="gcs-code"><?= $escape($json($rule->thresholds)) ?></pre></details></td></tr><?php endforeach; ?>
            </tbody></table></div>
            <?php if ($scan->samples !== []): ?><h2><?= $escape(Loc::getMessage('GCS_SAMPLES')) ?></h2><div class="gcs-table-wrap"><table class="gcs-table"><tbody><?php foreach ($scan->samples as $category => $ids): ?><tr><th><?= $escape($category) ?></th><td class="gcs-samples"><?= $escape(implode(', ', $ids)) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
        </div>
    </details>
</div>
<?php require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php'; ?>
