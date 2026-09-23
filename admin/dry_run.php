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
$escape = static fn (mixed $value): string => htmlspecialcharsbx((string) $value);
?>
<?php if ($error !== ''): ?><?php CAdminMessage::ShowMessage($escape($error)); ?><?php endif; ?>
<style>
.gcs-dry-page{max-width:1040px;margin-bottom:48px;color:#263238}.gcs-dry-intro{display:grid;grid-template-columns:46px 1fr;gap:16px;align-items:start;margin-bottom:17px;padding:19px;border:1px solid #c9d9e0;border-radius:10px;background:#fff}.gcs-dry-intro-icon{display:flex;align-items:center;justify-content:center;width:42px;height:42px;border-radius:50%;background:#e6f1f5;color:#315b72;font-size:22px;font-weight:bold}.gcs-dry-intro h2{margin:0 0 6px;font-size:20px}.gcs-dry-intro p{margin:0;color:#5e6e75;line-height:1.5}.gcs-dry-card{padding:22px;border:1px solid #d4dde2;border-radius:10px;background:#fff;box-shadow:0 2px 8px rgba(0,0,0,.04)}.gcs-dry-card-head{display:flex;justify-content:space-between;gap:16px;align-items:center;margin-bottom:20px}.gcs-dry-card-head h2{margin:0;font-size:19px}.gcs-dry-limit{display:inline-flex;align-items:center;gap:3px;margin:0;padding:7px 10px;border-radius:999px;background:#edf3f5;color:#52656e;font-size:12px;white-space:nowrap}.gcs-dry-grid{display:grid;grid-template-columns:minmax(220px,1fr) minmax(220px,1fr);gap:19px 24px}.gcs-dry-field{min-width:0}.gcs-dry-field-wide{grid-column:1/-1}.gcs-dry-label{display:flex;align-items:center;gap:3px;margin-bottom:8px;font-size:14px;font-weight:bold;color:#263238}.gcs-dry-card input[type=file],.gcs-dry-card input[type=text],.gcs-dry-card select{box-sizing:border-box;width:100%;min-height:38px}.gcs-dry-card input[type=text],.gcs-dry-card select{padding:7px 10px}.gcs-dry-card input[type=file]{padding:7px;border:1px solid #a9adb1;border-radius:3px;background:#fff}.gcs-dry-actions{display:flex;align-items:center;gap:10px;margin-top:22px;padding-top:18px;border-top:1px solid #e3e8eb}.gcs-dry-submit{min-height:38px;padding:0 18px;border:0;border-radius:5px;background:#315b72;color:#fff;font-weight:bold;cursor:pointer}.gcs-dry-submit:hover{background:#24495c}.gcs-dry-safe{color:#687780;font-size:12px}@media(max-width:760px){.gcs-dry-grid{grid-template-columns:1fr}.gcs-dry-field-wide{grid-column:auto}.gcs-dry-card-head{display:block}.gcs-dry-limit{margin-top:10px}}
</style>
<div class="gcs-dry-page">
    <section class="gcs-dry-intro">
        <div class="gcs-dry-intro-icon" aria-hidden="true">i</div>
        <div><h2><?= $escape(Loc::getMessage('GCS_DRY_HEADING')) ?></h2><p><?= $escape(Loc::getMessage('GCS_DRY_INTRO')) ?></p></div>
    </section>
    <form class="gcs-dry-card" method="post" enctype="multipart/form-data">
        <?= bitrix_sessid_post() ?>
        <div class="gcs-dry-card-head">
            <h2><?= $escape(Loc::getMessage('GCS_DRY_FORM_HEADING')) ?><?php ShowJSHint((string) Loc::getMessage('GCS_DRY_FORM_HINT')); ?></h2>
            <p class="gcs-dry-limit"><strong><?= $escape(Loc::getMessage('GCS_MAX_UPLOAD')) ?>:</strong> <?= $escape($maximumDisplay) ?><?php ShowJSHint((string) Loc::getMessage('GCS_HINT_MAX_UPLOAD')); ?></p>
        </div>
        <div class="gcs-dry-grid">
            <div class="gcs-dry-field gcs-dry-field-wide">
                <label class="gcs-dry-label" for="gcs-xml-file"><?= $escape(Loc::getMessage('GCS_DRY_FILE')) ?><?php ShowJSHint((string) Loc::getMessage('GCS_HINT_DRY_FILE')); ?></label>
                <input id="gcs-xml-file" type="file" name="xml_file" accept=".xml,text/xml,application/xml" required>
            </div>
            <div class="gcs-dry-field">
                <label class="gcs-dry-label" for="gcs-source-label"><?= $escape(Loc::getMessage('GCS_SOURCE_LABEL_FIELD')) ?><?php ShowJSHint((string) Loc::getMessage('GCS_HINT_SOURCE_LABEL')); ?></label>
                <input id="gcs-source-label" type="text" name="source_label" maxlength="255" placeholder="<?= $escape(Loc::getMessage('GCS_SOURCE_LABEL_PLACEHOLDER')) ?>">
            </div>
            <div class="gcs-dry-field">
                <label class="gcs-dry-label" for="gcs-source-key"><?= $escape(Loc::getMessage('GCS_BASELINE_FIELD')) ?><?php ShowJSHint((string) Loc::getMessage('GCS_HINT_BASELINE_SELECT')); ?></label>
                <select id="gcs-source-key" name="source_key">
                    <option value=""><?= $escape(Loc::getMessage('GCS_NEW_SOURCE')) ?></option>
                    <?php foreach ($sourceOptions as $key => $label): ?>
                        <option value="<?= $escape($key) ?>"><?= $escape($label !== '' ? $label : Loc::getMessage('GCS_UNKNOWN_SOURCE')) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="gcs-dry-actions"><button class="gcs-dry-submit" type="submit"><?= $escape(Loc::getMessage('GCS_RUN')) ?></button><span class="gcs-dry-safe"><?= $escape(Loc::getMessage('GCS_DRY_RUN_NOTICE')) ?></span></div>
    </form>
</div>
<?php require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php'; ?>
