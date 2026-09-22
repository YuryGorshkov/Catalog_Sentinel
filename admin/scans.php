<?php

declare(strict_types=1);

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Gorshkov\CatalogSentinel\Bitrix\Admin\AdminDataProvider;
use Gorshkov\CatalogSentinel\Bitrix\Admin\AdminGuard;

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php';
Loader::includeModule(AdminGuard::MODULE_ID) || die();
Loc::loadMessages(__FILE__);
AdminGuard::requireRead();
$provider = new AdminDataProvider();
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    AdminGuard::requirePost();
    $message = $provider->deleteScan((int) $_POST['delete_id']) ? 'deleted' : 'not_found';
}
$filters = [];
foreach (['STATUS', 'DECISION', 'DOCUMENT_KIND', 'SOURCE_KEY', 'IS_DRY_RUN', 'RULE_CODE', 'DATE_FROM', 'DATE_TO'] as $name) {
    $filters[$name] = trim((string) ($_GET[strtolower($name)] ?? ''));
}
$page = max(1, (int) ($_GET['page'] ?? 1));
$pageSize = 50;
$result = $provider->scans($filters, ($page - 1) * $pageSize, $pageSize);
$APPLICATION->SetTitle((string) Loc::getMessage('GCS_SCANS_TITLE'));
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';
?>
<?php if ($message !== ''): ?><div class="adm-info-message"><?= htmlspecialcharsbx($message) ?></div><?php endif; ?>
<form method="get">
    <input type="hidden" name="lang" value="<?= htmlspecialcharsbx(LANGUAGE_ID) ?>">
    <?php foreach (['status', 'decision', 'document_kind', 'source_key', 'rule_code', 'date_from', 'date_to'] as $name): ?>
        <label><?= htmlspecialcharsbx($name) ?> <input type="text" name="<?= htmlspecialcharsbx($name) ?>" value="<?= htmlspecialcharsbx((string) ($_GET[$name] ?? '')) ?>"></label>
    <?php endforeach; ?>
    <input type="submit" value="<?= htmlspecialcharsbx((string) Loc::getMessage('GCS_FILTER')) ?>">
</form>
<p><?= htmlspecialcharsbx((string) Loc::getMessage('GCS_TOTAL')) ?>: <?= (int) $result['total'] ?></p>
<table class="adm-list-table"><thead><tr class="adm-list-table-header"><td>ID</td><td><?= htmlspecialcharsbx((string) Loc::getMessage('GCS_DATE')) ?></td><td><?= htmlspecialcharsbx((string) Loc::getMessage('GCS_SOURCE')) ?></td><td><?= htmlspecialcharsbx((string) Loc::getMessage('GCS_KIND')) ?></td><td><?= htmlspecialcharsbx((string) Loc::getMessage('GCS_FILE')) ?></td><td><?= htmlspecialcharsbx((string) Loc::getMessage('GCS_STATUS')) ?></td><td><?= htmlspecialcharsbx((string) Loc::getMessage('GCS_DECISION')) ?></td><td><?= htmlspecialcharsbx((string) Loc::getMessage('GCS_OBJECTS')) ?></td><td></td></tr></thead><tbody>
<?php foreach ($result['rows'] as $row): ?>
<tr><td><?= (int) $row['ID'] ?></td><td><?= htmlspecialcharsbx((string) $row['CREATED_AT']) ?></td><td><?= htmlspecialcharsbx((string) $row['SOURCE_LABEL']) ?></td><td><?= htmlspecialcharsbx((string) $row['DOCUMENT_KIND']) ?></td><td><?= htmlspecialcharsbx((string) $row['FILE_NAME']) ?></td><td><?= htmlspecialcharsbx((string) $row['STATUS']) ?></td><td><?= htmlspecialcharsbx((string) $row['DECISION']) ?></td><td><?= (int) ($row['OBJECTS_TOTAL'] ?? 0) ?></td><td><a href="gorshkov_catalogsentinel_scan.php?id=<?= (int) $row['ID'] ?>&amp;lang=<?= urlencode(LANGUAGE_ID) ?>"><?= htmlspecialcharsbx((string) Loc::getMessage('GCS_OPEN')) ?></a><?php if (AdminGuard::right() >= 'W'): ?><form method="post" onsubmit="return confirm('<?= CUtil::JSEscape((string) Loc::getMessage('GCS_DELETE_CONFIRM')) ?>')"><?= bitrix_sessid_post() ?><input type="hidden" name="delete_id" value="<?= (int) $row['ID'] ?>"><button><?= htmlspecialcharsbx((string) Loc::getMessage('GCS_DELETE')) ?></button></form><?php endif; ?></td></tr>
<?php endforeach; ?>
</tbody></table>
<?php if ($page > 1): ?><a href="?lang=<?= urlencode(LANGUAGE_ID) ?>&amp;page=<?= $page - 1 ?>">&larr;</a><?php endif; ?>
<?php if ($page * $pageSize < $result['total']): ?><a href="?lang=<?= urlencode(LANGUAGE_ID) ?>&amp;page=<?= $page + 1 ?>">&rarr;</a><?php endif; ?>
<?php require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php'; ?>
