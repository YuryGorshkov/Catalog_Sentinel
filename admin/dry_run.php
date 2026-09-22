<?php

declare(strict_types=1);

use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Gorshkov\CatalogSentinel\Bitrix\Admin\AdminGuard;
use Gorshkov\CatalogSentinel\Bitrix\Admin\DryRunUpload;
use Gorshkov\CatalogSentinel\Infrastructure\Support\ByteSize;

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php';
Loader::includeModule(AdminGuard::MODULE_ID) || die();
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
$sourceOptions = (new \Gorshkov\CatalogSentinel\Bitrix\Admin\AdminDataProvider())->sourceOptions();
$APPLICATION->SetTitle((string) Loc::getMessage('GCS_DRY_RUN_TITLE'));
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';
?>
<?php if ($error !== ''): ?><?php CAdminMessage::ShowMessage(htmlspecialcharsbx($error)); ?><?php endif; ?>
<div class="adm-info-message"><?= htmlspecialcharsbx((string) Loc::getMessage('GCS_DRY_RUN_NOTICE')) ?></div>
<p><?= htmlspecialcharsbx((string) Loc::getMessage('GCS_MAX_UPLOAD')) ?>: <?= (int) $maximum ?> bytes</p>
<form method="post" enctype="multipart/form-data"><?= bitrix_sessid_post() ?><input type="file" name="xml_file" accept=".xml,text/xml,application/xml" required><input type="text" name="source_label" maxlength="255" placeholder="source"><select name="source_key"><option value=""><?= htmlspecialcharsbx((string) Loc::getMessage('GCS_NEW_SOURCE')) ?></option><?php foreach ($sourceOptions as $key => $label): ?><option value="<?= htmlspecialcharsbx($key) ?>"><?= htmlspecialcharsbx($label . ' — ' . $key) ?></option><?php endforeach; ?></select><input type="submit" value="<?= htmlspecialcharsbx((string) Loc::getMessage('GCS_RUN')) ?>"></form>
<?php require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php'; ?>
