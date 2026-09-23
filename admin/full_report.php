<?php

declare(strict_types=1);

use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Gorshkov\CatalogSentinel\Bitrix\Admin\AdminDataProvider;
use Gorshkov\CatalogSentinel\Bitrix\Admin\AdminGuard;
use Gorshkov\CatalogSentinel\Infrastructure\Support\ByteSize;
use Gorshkov\CatalogSentinel\Infrastructure\Xml\AffectedProductStreamer;

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php';
Loader::includeModule('gorshkov.catalogsentinel') || die();
Loc::loadMessages(__FILE__);
AdminGuard::requireRead();

$id = max(0, (int) ($_REQUEST['id'] ?? 0));
$scan = (new AdminDataProvider())->scan($id);
$supportedRules = ['stock.zero_spike', 'price.zero_spike', 'property.empty_spike', 'identifier.missing_ratio'];
$ruleCodes = [];
$propertyIds = [];
if ($scan !== null) {
    foreach ($scan->ruleResults as $rule) {
        if (!in_array($rule->ruleCode, $supportedRules, true) || !in_array($rule->outcome, ['WARN', 'BLOCK'], true)) {
            continue;
        }
        $ruleCodes[] = $rule->ruleCode;
        if ($rule->ruleCode === 'property.empty_spike' && is_string($rule->messageParams['properties'] ?? null)) {
            foreach (explode(',', (string) $rule->messageParams['properties']) as $propertyId) {
                $propertyId = trim($propertyId);
                if ($propertyId !== '') {
                    $propertyIds[] = $propertyId;
                }
            }
        }
    }
}
$ruleCodes = array_values(array_unique($ruleCodes));
$propertyIds = array_values(array_unique($propertyIds));
$ruleLabels = [
    'stock.zero_spike' => (string) Loc::getMessage('GCS_RULE_STOCK_ZERO_SPIKE'),
    'price.zero_spike' => (string) Loc::getMessage('GCS_RULE_PRICE_ZERO_SPIKE'),
    'property.empty_spike' => (string) Loc::getMessage('GCS_RULE_PROPERTY_EMPTY_SPIKE'),
    'identifier.missing_ratio' => (string) Loc::getMessage('GCS_RULE_IDENTIFIER_MISSING'),
];
$displayRules = array_map(static fn (string $code): string => $ruleLabels[$code] ?? $code, $ruleCodes);
$maximum = ByteSize::uploadLimit((int) Option::get(AdminGuard::MODULE_ID, 'dry_run_max_bytes', '52428800'));
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['download'])) {
    $temporary = null;
    try {
        AdminGuard::requirePost();
        if ($scan === null || $ruleCodes === []) {
            throw new RuntimeException('unavailable');
        }
        $file = is_array($_FILES['xml_file'] ?? null) ? $_FILES['xml_file'] : [];
        $uploadError = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        $size = (int) ($file['size'] ?? 0);
        $uploaded = (string) ($file['tmp_name'] ?? '');
        if ($uploadError !== UPLOAD_ERR_OK || $size < 1 || $size > $maximum || !is_uploaded_file($uploaded)) {
            throw new RuntimeException('upload');
        }
        if ($size !== $scan->file->size) {
            throw new RuntimeException('mismatch');
        }
        $safeName = preg_replace('/[^A-Za-z0-9._-]+/u', '_', basename((string) ($file['name'] ?? 'report.xml'))) ?: 'report.xml';
        $temporary = CTempFile::GetFileName('gcs_report_' . bin2hex(random_bytes(8)) . '_' . substr($safeName, -100));
        CheckDirPath(dirname($temporary) . '/');
        if (!move_uploaded_file($uploaded, $temporary)) {
            throw new RuntimeException('upload');
        }
        if ($scan->file->contentHash !== null && !hash_equals($scan->file->contentHash, (string) hash_file('sha256', $temporary))) {
            throw new RuntimeException('mismatch');
        }

        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="catalog-sentinel-scan-' . $id . '-products.csv"');
        header('X-Content-Type-Options: nosniff');
        echo "\xEF\xBB\xBF";
        $output = fopen('php://output', 'wb');
        if ($output === false) {
            throw new RuntimeException('stream');
        }
        $csv = static function (mixed $value): string {
            $cell = (string) $value;
            return preg_match('/^[=+\-@\t\r]/u', $cell) === 1 ? "'" . $cell : $cell;
        };
        fputcsv($output, [
            Loc::getMessage('GCS_FULL_REPORT_COL_RULE'),
            Loc::getMessage('GCS_FULL_REPORT_COL_NAME'),
            Loc::getMessage('GCS_FULL_REPORT_COL_ID'),
            Loc::getMessage('GCS_FULL_REPORT_COL_VALUE'),
        ], ';');
        foreach ((new AffectedProductStreamer())->rows($temporary, $ruleCodes, $propertyIds) as $row) {
            $parts = explode(':', $row['rule_code'], 2);
            $ruleLabel = $ruleLabels[$parts[0]] ?? $parts[0];
            if (isset($parts[1]) && $parts[1] !== '') {
                $ruleLabel .= ' (' . $parts[1] . ')';
            }
            fputcsv($output, array_map($csv, [
                $ruleLabel,
                $row['product_name'],
                $row['external_id'],
                $row['measured_value'],
            ]), ';');
        }
        fclose($output);
        if (is_file($temporary)) {
            unlink($temporary);
        }
        exit;
    } catch (Throwable $exception) {
        if ($temporary !== null && is_file($temporary)) {
            unlink($temporary);
        }
        $error = match ($exception->getMessage()) {
            'upload' => (string) Loc::getMessage('GCS_FULL_REPORT_UPLOAD_ERROR'),
            'mismatch' => (string) Loc::getMessage('GCS_FULL_REPORT_MISMATCH'),
            'unavailable' => (string) Loc::getMessage('GCS_FULL_REPORT_UNAVAILABLE'),
            default => (string) Loc::getMessage('GCS_FULL_REPORT_PARSE_ERROR'),
        };
    }
}

$APPLICATION->SetTitle((string) Loc::getMessage('GCS_FULL_REPORT_TITLE'));
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';
$escape = static fn (mixed $value): string => htmlspecialcharsbx((string) $value);
?>
<style>
.gcs-report{max-width:820px;margin-bottom:40px;color:#263238}.gcs-report-card{padding:22px;border:1px solid #d7e0e4;border-radius:10px;background:#fff}.gcs-report-card h2{margin:0 0 10px;font-size:21px}.gcs-report-card p{margin:7px 0;color:#5e6d74;line-height:1.55}.gcs-report-meta{display:grid;grid-template-columns:160px 1fr;gap:8px 16px;margin:18px 0;padding:15px;border-radius:7px;background:#f4f7f8}.gcs-report-meta dt{color:#687780}.gcs-report-meta dd{margin:0;font-weight:600;overflow-wrap:anywhere}.gcs-report-upload{margin-top:20px;padding-top:18px;border-top:1px solid #e1e7ea}.gcs-report-upload label{display:block;margin-bottom:8px;font-weight:600}.gcs-report-actions{display:flex;align-items:center;gap:10px;margin-top:16px}.gcs-report-button{min-height:38px;padding:0 18px;border:0;border-radius:5px;background:#315b72;color:#fff;font-weight:bold;cursor:pointer}.gcs-report-back{color:#315b72;text-decoration:none}@media(max-width:600px){.gcs-report-meta{grid-template-columns:1fr;gap:4px}.gcs-report-meta dd{margin-bottom:8px}}
</style>
<div class="gcs-report">
    <?php if ($error !== ''): ?><div class="adm-info-message adm-info-message-red"><?= $escape($error) ?></div><?php endif; ?>
    <?php if ($scan === null): ?>
        <?php CAdminMessage::ShowMessage((string) Loc::getMessage('GCS_NOT_FOUND')); ?>
    <?php elseif ($ruleCodes === []): ?>
        <div class="adm-info-message"><?= $escape(Loc::getMessage('GCS_FULL_REPORT_UNAVAILABLE')) ?></div>
    <?php else: ?>
        <section class="gcs-report-card">
            <h2><?= $escape(Loc::getMessage('GCS_FULL_REPORT_HEADING')) ?></h2>
            <p><?= $escape(Loc::getMessage('GCS_FULL_REPORT_EXPLAIN')) ?></p>
            <p><?= $escape(Loc::getMessage('GCS_FULL_REPORT_PRIVACY')) ?></p>
            <dl class="gcs-report-meta">
                <dt><?= $escape(Loc::getMessage('GCS_FILE')) ?></dt><dd><?= $escape($scan->file->fileName) ?></dd>
                <dt><?= $escape(Loc::getMessage('GCS_FULL_REPORT_EXPECTED_SIZE')) ?></dt><dd><?= number_format($scan->file->size, 0, ',', ' ') ?> <?= $escape(Loc::getMessage('GCS_BYTES')) ?></dd>
                <dt><?= $escape(Loc::getMessage('GCS_FULL_REPORT_RULES')) ?></dt><dd><?= $escape(implode(', ', $displayRules)) ?></dd>
                <dt><?= $escape(Loc::getMessage('GCS_MAX_UPLOAD')) ?></dt><dd><?= number_format($maximum, 0, ',', ' ') ?> <?= $escape(Loc::getMessage('GCS_BYTES')) ?></dd>
            </dl>
            <form class="gcs-report-upload" method="post" enctype="multipart/form-data">
                <?= bitrix_sessid_post() ?>
                <input type="hidden" name="id" value="<?= $id ?>">
                <label for="gcs-full-report-file"><?= $escape(Loc::getMessage('GCS_FULL_REPORT_SELECT_FILE')) ?></label>
                <input id="gcs-full-report-file" type="file" name="xml_file" accept=".xml,text/xml,application/xml" required>
                <div class="gcs-report-actions">
                    <button class="gcs-report-button" type="submit" name="download" value="Y"><?= $escape(Loc::getMessage('GCS_FULL_REPORT_DOWNLOAD')) ?></button>
                    <a class="gcs-report-back" href="gorshkov_catalogsentinel_scan.php?id=<?= $id ?>&amp;lang=<?= urlencode(LANGUAGE_ID) ?>"><?= $escape(Loc::getMessage('GCS_FULL_REPORT_BACK')) ?></a>
                </div>
            </form>
        </section>
    <?php endif; ?>
</div>
<?php require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php'; ?>
