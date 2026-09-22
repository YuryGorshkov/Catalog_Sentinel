<?php

defined('B_PROLOG_INCLUDED') || die();
?>
<form action="<?= htmlspecialcharsbx($APPLICATION->GetCurPage()) ?>" method="post">
    <?= bitrix_sessid_post() ?>
    <input type="hidden" name="lang" value="<?= htmlspecialcharsbx(LANGUAGE_ID) ?>">
    <input type="hidden" name="id" value="gorshkov.catalogsentinel">
    <input type="hidden" name="uninstall" value="Y">
    <input type="hidden" name="step" value="2">
    <label>
        <input type="checkbox" name="delete_data" value="Y">
        <?= htmlspecialcharsbx(GetMessage('GCS_DELETE_DATA') ?: 'Delete module logs and settings') ?>
    </label>
    <input type="submit" value="<?= htmlspecialcharsbx(GetMessage('MOD_UNINST_DEL')) ?>">
</form>
