<?php

defined('B_PROLOG_INCLUDED') || die();
?>
<form action="<?= htmlspecialcharsbx($APPLICATION->GetCurPage()) ?>" method="post">
    <?= bitrix_sessid_post() ?>
    <input type="hidden" name="lang" value="<?= htmlspecialcharsbx(LANGUAGE_ID) ?>">
    <input type="submit" name="" value="<?= htmlspecialcharsbx(GetMessage('MOD_BACK')) ?>">
</form>
