<?php

declare(strict_types=1);

use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Gorshkov\CatalogSentinel\Bitrix\Admin\AdminGuard;
use Gorshkov\CatalogSentinel\Bitrix\Admin\DryRunUpload;
use Gorshkov\CatalogSentinel\Infrastructure\Support\ByteSize;

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php';
Loader::includeModule('gorshkov.catalogsentinel') || die();
Loc::loadMessages(__FILE__);
AdminGuard::requireWrite();
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        AdminGuard::requirePost();
        $outcome = (new DryRunUpload())->execute(
            $_FILES['xml_file'] ?? [],
            (string) ($_POST['source_label'] ?? ''),
            (string) ($_POST['source_key'] ?? '') ?: null,
        );
        if ($outcome->scan?->id !== null) {
            LocalRedirect('gorshkov_catalogsentinel_scan.php?id=' . $outcome->scan->id . '&lang=' . urlencode(LANGUAGE_ID));
        }
        $error = 'Scan was not persisted.';
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}
$maximum = ByteSize::uploadLimit((int) Option::get(AdminGuard::MODULE_ID, 'dry_run_max_bytes', '52428800'));
$maximumDisplay = $maximum >= 1048576
    ? number_format($maximum / 1048576, 0, ',', ' ') . ' MB'
    : number_format($maximum / 1024, 0, ',', ' ') . ' KB';
$sourceOptions = (new \Gorshkov\CatalogSentinel\Bitrix\Admin\AdminDataProvider())->sourceOptions();
$APPLICATION->SetTitle((string) Loc::getMessage('GCS_DRY_RUN_TITLE'));
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';
?>
<?php if ($error !== ''): ?><?php CAdminMessage::ShowMessage(htmlspecialcharsbx($error)); ?><?php endif; ?>
<div class="adm-info-message"><?= htmlspecialcharsbx((string) Loc::getMessage('GCS_DRY_RUN_NOTICE')) ?></div>
<style>
.gcs-dry-card{max-width:1000px;margin-top:18px;padding:24px;border:1px solid #d4dde2;border-radius:10px;background:#fff;box-shadow:0 2px 8px rgba(0,0,0,.04)}.gcs-dry-limit{margin:0 0 22px;color:#586970}.gcs-dry-grid{display:grid;grid-template-columns:minmax(220px,1fr) minmax(220px,1fr);gap:22px 26px}.gcs-dry-field{min-width:0}.gcs-dry-field-wide{grid-column:1/-1}.gcs-dry-label{display:block;margin-bottom:8px;font-size:14px;font-weight:bold;color:#263238}.gcs-dry-hint{display:block;margin-top:7px;color:#6d7b82;font-size:12px;line-height:1.4}.gcs-dry-card input[type=file],.gcs-dry-card input[type=text],.gcs-dry-card select{box-sizing:border-box;width:100%;min-height:38px}.gcs-dry-card input[type=text],.gcs-dry-card select{padding:7px 10px}.gcs-dry-card input[type=file]{padding:7px;border:1px solid #a9adb1;border-radius:2px;background:#fff}.gcs-dry-actions{display:flex;justify-content:flex-start;margin-top:24px;padding-top:20px;border-top:1px solid #e3e8eb}.gcs-dry-submit{min-height:38px;padding:0 18px;border:0;border-radius:5px;background:#315b72;color:#fff;font-weight:bold;cursor:pointer}.gcs-dry-submit:hover{background:#24495c}@media(max-width:760px){.gcs-dry-grid{grid-template-columns:1fr}.gcs-dry-field-wide{grid-column:auto}}
</style>
<form class="gcs-dry-card" method="post" enctype="multipart/form-data">
    <?= bitrix_sessid_post() ?>
    <p class="gcs-dry-limit"><strong><?= htmlspecialcharsbx((string) Loc::getMessage('GCS_MAX_UPLOAD')) ?>:</strong> <?= htmlspecialcharsbx($maximumDisplay) ?></p>
    <div class="gcs-dry-grid">
        <div class="gcs-dry-field gcs-dry-field-wide">
            <label class="gcs-dry-label" for="gcs-xml-file"><?= htmlspecialcharsbx((string) Loc::getMessage('GCS_DRY_FILE')) ?></label>
            <input id="gcs-xml-file" type="file" name="xml_file" accept=".xml,text/xml,application/xml" required>
            <span class="gcs-dry-hint"><?= htmlspecialcharsbx((string) Loc::getMessage('GCS_DRY_FILE_HINT')) ?></span>
        </div>
        <div class="gcs-dry-field">
            <label class="gcs-dry-label" for="gcs-source-label"><?= htmlspecialcharsbx((string) Loc::getMessage('GCS_SOURCE_LABEL_FIELD')) ?></label>
            <input id="gcs-source-label" type="text" name="source_label" maxlength="255" placeholder="<?= htmlspecialcharsbx((string) Loc::getMessage('GCS_SOURCE_LABEL_PLACEHOLDER')) ?>">
            <span class="gcs-dry-hint"><?= htmlspecialcharsbx((string) Loc::getMessage('GCS_SOURCE_LABEL_HINT')) ?></span>
        </div>
        <div class="gcs-dry-field">
            <label class="gcs-dry-label" for="gcs-source-key"><?= htmlspecialcharsbx((string) Loc::getMessage('GCS_BASELINE_FIELD')) ?></label>
            <select id="gcs-source-key" name="source_key">
                <option value=""><?= htmlspecialcharsbx((string) Loc::getMessage('GCS_NEW_SOURCE')) ?></option>
                <?php foreach ($sourceOptions as $key => $label): ?>
                    <option value="<?= htmlspecialcharsbx($key) ?>"><?= htmlspecialcharsbx($label . ' — ' . $key) ?></option>
                <?php endforeach; ?>
            </select>
            <span class="gcs-dry-hint"><?= htmlspecialcharsbx((string) Loc::getMessage('GCS_BASELINE_HINT')) ?></span>
        </div>
    </div>
    <div class="gcs-dry-actions"><button class="gcs-dry-submit" type="submit"><?= htmlspecialcharsbx((string) Loc::getMessage('GCS_RUN')) ?></button></div>
</form>
<?php require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php'; ?>
