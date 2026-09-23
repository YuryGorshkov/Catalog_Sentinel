<?php

declare(strict_types=1);

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Gorshkov\CatalogSentinel\Bitrix\Admin\AdminDataProvider;
use Gorshkov\CatalogSentinel\Bitrix\Admin\AdminGuard;

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php';
Loader::includeModule('gorshkov.catalogsentinel') || die();
Loc::loadMessages(__FILE__);
AdminGuard::requireRead();

$provider = new AdminDataProvider();
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    AdminGuard::requirePost();
    $message = $provider->deleteScan((int) $_POST['delete_id'])
        ? (string) Loc::getMessage('GCS_DELETE_SUCCESS')
        : (string) Loc::getMessage('GCS_NOT_FOUND');
}

$filterFields = ['STATUS', 'DECISION', 'DOCUMENT_KIND', 'SOURCE_KEY', 'IS_DRY_RUN', 'RULE_CODE', 'DATE_FROM', 'DATE_TO'];
$filters = [];
foreach ($filterFields as $name) {
    $filters[$name] = trim((string) ($_GET[strtolower($name)] ?? ''));
}
$page = max(1, (int) ($_GET['page'] ?? 1));
$pageSize = 50;
$result = $provider->scans($filters, ($page - 1) * $pageSize, $pageSize);
$sources = $provider->sourceOptions();
$statusOptions = ['SCANNING', 'OBSERVED', 'ALLOWED', 'BLOCKED', 'IMPORTED', 'UNSUPPORTED', 'SCAN_ERROR_ALLOWED', 'SCAN_ERROR_BLOCKED', 'STALE', 'DRY_RUN'];
$decisionOptions = ['PASS', 'WARN', 'BLOCK', 'ERROR'];
$kindOptions = ['CATALOG', 'OFFERS', 'CLASSIFIER', 'UNKNOWN_COMMERCE_ML', 'NOT_COMMERCE_ML', 'MALFORMED'];
$ruleOptions = ['xml.well_formed', 'document.supported', 'document.item_count_drop', 'stock.zero_spike', 'price.zero_spike', 'property.empty_spike', 'identifier.missing_ratio', 'file.size_drop'];
$query = ['lang' => LANGUAGE_ID];
foreach ($filters as $name => $value) {
    if ($value !== '') {
        $query[strtolower($name)] = $value;
    }
}
$APPLICATION->SetTitle((string) Loc::getMessage('GCS_SCANS_TITLE'));
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';
$escape = static fn (mixed $value): string => htmlspecialcharsbx((string) $value);
$formatDate = static function (mixed $value): string {
    try {
        return (new DateTimeImmutable((string) $value))->format('d.m.Y H:i:s');
    } catch (Throwable) {
        return (string) $value;
    }
};
$statusLabel = static fn (string $value): string => (string) (Loc::getMessage('GCS_STATUS_' . $value) ?: $value);
$decisionLabel = static fn (string $value): string => (string) (Loc::getMessage('GCS_OUTCOME_' . $value) ?: $value);
$kindLabel = static fn (string $value): string => (string) (Loc::getMessage('GCS_KIND_' . $value) ?: $value);
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
$ruleLabel = static fn (string $value): string => $ruleLabels[$value] ?? $value;
?>
<style>
.gcs-scans{max-width:1280px;margin-bottom:45px;color:#263238;font-size:14px}.gcs-intro{margin:0 0 17px;color:#596a72;font-size:14px;line-height:1.5}.gcs-filter{padding:18px;border:1px solid #d6e0e4;border-radius:10px;background:#fff;box-shadow:0 2px 7px rgba(0,0,0,.04)}.gcs-filter h2{display:flex;align-items:center;gap:3px;margin:0 0 15px;font-size:18px}.gcs-filter-grid{display:grid;grid-template-columns:repeat(4,minmax(170px,1fr));gap:14px 16px}.gcs-field{min-width:0}.gcs-field label{display:flex;align-items:center;gap:3px;margin:0 0 6px;font-weight:600;line-height:1.25}.gcs-field input,.gcs-field select{box-sizing:border-box;width:100%;min-height:36px;padding:6px 9px}.gcs-filter-actions{display:flex;gap:10px;align-items:center;margin-top:16px}.gcs-button{display:inline-flex;align-items:center;justify-content:center;box-sizing:border-box;min-height:36px;padding:0 15px;border:1px solid #9eafb8;border-radius:5px;background:#fff;color:#315b72;text-decoration:none;cursor:pointer}.gcs-button.primary{border-color:#315b72;background:#315b72;color:#fff;font-weight:600}.gcs-summary{display:flex;justify-content:space-between;align-items:center;margin:19px 0 10px}.gcs-summary strong{font-size:17px}.gcs-table-wrap{overflow:auto;border:1px solid #d6e0e4;border-radius:10px;background:#fff}.gcs-table{width:100%;border-collapse:collapse}.gcs-table th,.gcs-table td{padding:12px 11px;border-bottom:1px solid #e3e9ec;text-align:left;vertical-align:middle}.gcs-table th{background:#f4f7f8;color:#5c6c74;font-size:12px;text-transform:uppercase;letter-spacing:.03em;white-space:nowrap}.gcs-table tr:last-child td{border-bottom:0}.gcs-file{max-width:260px;font-weight:600;overflow-wrap:anywhere}.gcs-muted{display:block;margin-top:3px;color:#76858c;font-size:12px}.gcs-badge{display:inline-flex;padding:5px 8px;border-radius:999px;background:#e9eff2;color:#40535c;font-size:12px;font-weight:600;white-space:nowrap}.gcs-badge.block,.gcs-badge.error{background:#fde4e2;color:#a22017}.gcs-badge.warn{background:#fff0c7;color:#875d00}.gcs-badge.pass{background:#dff4e7;color:#14663b}.gcs-actions{display:flex;gap:7px;align-items:center;white-space:nowrap}.gcs-actions form{display:inline;margin:0}.gcs-link{color:#315b72;text-decoration:none;font-weight:600}.gcs-delete{padding:0;border:0;background:transparent;color:#ad3128;cursor:pointer}.gcs-empty{padding:30px!important;text-align:center!important;color:#687780}.gcs-pages{display:flex;gap:8px;align-items:center;margin-top:14px}.gcs-pages a,.gcs-pages span{display:inline-flex;align-items:center;min-height:34px;padding:0 11px;border:1px solid #cad5da;border-radius:5px;background:#fff;color:#315b72;text-decoration:none}.gcs-pages span{background:#edf2f4;color:#263238;font-weight:600}@media(max-width:1000px){.gcs-filter-grid{grid-template-columns:repeat(2,minmax(170px,1fr))}}@media(max-width:620px){.gcs-filter-grid{grid-template-columns:1fr}.gcs-summary{display:block}}
</style>
<div class="gcs-scans">
    <p class="gcs-intro"><?= $escape(Loc::getMessage('GCS_SCANS_INTRO')) ?></p>
    <?php if ($message !== ''): ?><div class="adm-info-message"><?= $escape($message) ?></div><?php endif; ?>
    <form class="gcs-filter" method="get">
        <input type="hidden" name="lang" value="<?= $escape(LANGUAGE_ID) ?>">
        <h2><?= $escape(Loc::getMessage('GCS_FILTER_HEADING')) ?><?php ShowJSHint((string) Loc::getMessage('GCS_HINT_FILTERS')); ?></h2>
        <div class="gcs-filter-grid">
            <div class="gcs-field"><label for="gcs-status"><?= $escape(Loc::getMessage('GCS_STATUS')) ?><?php ShowJSHint((string) Loc::getMessage('GCS_HINT_FILTER_STATUS')); ?></label><select id="gcs-status" name="status"><option value=""><?= $escape(Loc::getMessage('GCS_ALL')) ?></option><?php foreach ($statusOptions as $value): ?><option value="<?= $escape($value) ?>"<?= $filters['STATUS'] === $value ? ' selected' : '' ?>><?= $escape($statusLabel($value)) ?></option><?php endforeach; ?></select></div>
            <div class="gcs-field"><label for="gcs-decision"><?= $escape(Loc::getMessage('GCS_DECISION')) ?><?php ShowJSHint((string) Loc::getMessage('GCS_HINT_FILTER_DECISION')); ?></label><select id="gcs-decision" name="decision"><option value=""><?= $escape(Loc::getMessage('GCS_ALL')) ?></option><?php foreach ($decisionOptions as $value): ?><option value="<?= $escape($value) ?>"<?= $filters['DECISION'] === $value ? ' selected' : '' ?>><?= $escape($decisionLabel($value)) ?></option><?php endforeach; ?></select></div>
            <div class="gcs-field"><label for="gcs-kind"><?= $escape(Loc::getMessage('GCS_KIND')) ?><?php ShowJSHint((string) Loc::getMessage('GCS_HINT_FILTER_KIND')); ?></label><select id="gcs-kind" name="document_kind"><option value=""><?= $escape(Loc::getMessage('GCS_ALL')) ?></option><?php foreach ($kindOptions as $value): ?><option value="<?= $escape($value) ?>"<?= $filters['DOCUMENT_KIND'] === $value ? ' selected' : '' ?>><?= $escape($kindLabel($value)) ?></option><?php endforeach; ?></select></div>
            <div class="gcs-field"><label for="gcs-source"><?= $escape(Loc::getMessage('GCS_SOURCE')) ?><?php ShowJSHint((string) Loc::getMessage('GCS_HINT_FILTER_SOURCE')); ?></label><select id="gcs-source" name="source_key"><option value=""><?= $escape(Loc::getMessage('GCS_ALL')) ?></option><?php foreach ($sources as $key => $label): ?><option value="<?= $escape($key) ?>"<?= $filters['SOURCE_KEY'] === $key ? ' selected' : '' ?>><?= $escape($label) ?></option><?php endforeach; ?></select></div>
            <div class="gcs-field"><label for="gcs-rule"><?= $escape(Loc::getMessage('GCS_FILTER_RULE')) ?><?php ShowJSHint((string) Loc::getMessage('GCS_HINT_FILTER_RULE')); ?></label><select id="gcs-rule" name="rule_code"><option value=""><?= $escape(Loc::getMessage('GCS_ALL')) ?></option><?php foreach ($ruleOptions as $value): ?><option value="<?= $escape($value) ?>"<?= $filters['RULE_CODE'] === $value ? ' selected' : '' ?>><?= $escape($ruleLabel($value)) ?></option><?php endforeach; ?></select></div>
            <div class="gcs-field"><label for="gcs-run-kind"><?= $escape(Loc::getMessage('GCS_FILTER_RUN_KIND')) ?><?php ShowJSHint((string) Loc::getMessage('GCS_HINT_FILTER_RUN_KIND')); ?></label><select id="gcs-run-kind" name="is_dry_run"><option value=""><?= $escape(Loc::getMessage('GCS_ALL')) ?></option><option value="N"<?= $filters['IS_DRY_RUN'] === 'N' ? ' selected' : '' ?>><?= $escape(Loc::getMessage('GCS_RUN_AUTOMATIC')) ?></option><option value="Y"<?= $filters['IS_DRY_RUN'] === 'Y' ? ' selected' : '' ?>><?= $escape(Loc::getMessage('GCS_RUN_MANUAL')) ?></option></select></div>
            <div class="gcs-field"><label for="gcs-date-from"><?= $escape(Loc::getMessage('GCS_DATE_FROM')) ?><?php ShowJSHint((string) Loc::getMessage('GCS_HINT_FILTER_DATE_FROM')); ?></label><input id="gcs-date-from" type="date" name="date_from" value="<?= $escape($filters['DATE_FROM']) ?>"></div>
            <div class="gcs-field"><label for="gcs-date-to"><?= $escape(Loc::getMessage('GCS_DATE_TO')) ?><?php ShowJSHint((string) Loc::getMessage('GCS_HINT_FILTER_DATE_TO')); ?></label><input id="gcs-date-to" type="date" name="date_to" value="<?= $escape($filters['DATE_TO']) ?>"></div>
        </div>
        <div class="gcs-filter-actions"><button class="gcs-button primary" type="submit"><?= $escape(Loc::getMessage('GCS_FILTER')) ?></button><a class="gcs-button" href="gorshkov_catalogsentinel_scans.php?lang=<?= urlencode(LANGUAGE_ID) ?>"><?= $escape(Loc::getMessage('GCS_CLEAR_FILTER')) ?></a></div>
    </form>
    <div class="gcs-summary"><strong><?= $escape(Loc::getMessage('GCS_SCANS_FOUND', ['#COUNT#' => number_format((int) $result['total'], 0, ',', ' ')])) ?></strong><span><?= $escape(Loc::getMessage('GCS_SCANS_HINT')) ?></span></div>
    <div class="gcs-table-wrap"><table class="gcs-table">
        <thead><tr><th>ID</th><th><?= $escape(Loc::getMessage('GCS_DATE')) ?></th><th><?= $escape(Loc::getMessage('GCS_FILE')) ?></th><th><?= $escape(Loc::getMessage('GCS_SOURCE')) ?></th><th><?= $escape(Loc::getMessage('GCS_STATUS')) ?></th><th><?= $escape(Loc::getMessage('GCS_DECISION')) ?></th><th><?= $escape(Loc::getMessage('GCS_OBJECTS')) ?></th><th><?= $escape(Loc::getMessage('GCS_ACTIONS')) ?></th></tr></thead>
        <tbody>
        <?php if ($result['rows'] === []): ?><tr><td class="gcs-empty" colspan="8"><?= $escape(Loc::getMessage('GCS_NO_SCANS')) ?></td></tr><?php endif; ?>
        <?php foreach ($result['rows'] as $row): ?>
            <?php $decisionClass = match ((string) $row['DECISION']) {'BLOCK' => 'block', 'WARN' => 'warn', 'ERROR' => 'error', default => 'pass'}; ?>
            <tr>
                <td><?= (int) $row['ID'] ?></td>
                <td><?= $escape($formatDate($row['CREATED_AT'])) ?><span class="gcs-muted"><?= ($row['IS_DRY_RUN'] ?? 'N') === 'Y' ? $escape(Loc::getMessage('GCS_RUN_MANUAL')) : $escape(Loc::getMessage('GCS_RUN_AUTOMATIC')) ?></span></td>
                <td class="gcs-file"><?= $escape($row['FILE_NAME']) ?><span class="gcs-muted"><?= $escape($kindLabel((string) $row['DOCUMENT_KIND'])) ?></span></td>
                <td><?= $escape($row['SOURCE_LABEL']) ?></td>
                <td><span class="gcs-badge"><?= $escape($statusLabel((string) $row['STATUS'])) ?></span></td>
                <td><span class="gcs-badge <?= $escape($decisionClass) ?>"><?= $escape($decisionLabel((string) $row['DECISION'])) ?></span></td>
                <td><?= number_format((int) ($row['OBJECTS_TOTAL'] ?? 0), 0, ',', ' ') ?></td>
                <td><div class="gcs-actions"><a class="gcs-link" href="gorshkov_catalogsentinel_scan.php?id=<?= (int) $row['ID'] ?>&amp;lang=<?= urlencode(LANGUAGE_ID) ?>"><?= $escape(Loc::getMessage('GCS_OPEN')) ?></a><?php if (AdminGuard::right() >= 'W'): ?><form method="post" onsubmit="return confirm('<?= CUtil::JSEscape((string) Loc::getMessage('GCS_DELETE_CONFIRM')) ?>')"><?= bitrix_sessid_post() ?><input type="hidden" name="delete_id" value="<?= (int) $row['ID'] ?>"><button class="gcs-delete" type="submit"><?= $escape(Loc::getMessage('GCS_DELETE')) ?></button></form><?php endif; ?></div></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php if ($result['total'] > $pageSize): ?><nav class="gcs-pages" aria-label="<?= $escape(Loc::getMessage('GCS_PAGES')) ?>"><?php if ($page > 1): ?><a href="?<?= $escape(http_build_query($query + ['page' => $page - 1])) ?>">← <?= $escape(Loc::getMessage('GCS_PREVIOUS')) ?></a><?php endif; ?><span><?= $escape(Loc::getMessage('GCS_PAGE_NUMBER', ['#NUMBER#' => (string) $page])) ?></span><?php if ($page * $pageSize < $result['total']): ?><a href="?<?= $escape(http_build_query($query + ['page' => $page + 1])) ?>"><?= $escape(Loc::getMessage('GCS_NEXT')) ?> →</a><?php endif; ?></nav><?php endif; ?>
</div>
<?php require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php'; ?>
