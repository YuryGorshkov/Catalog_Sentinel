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
Loader::includeModule(AdminGuard::MODULE_ID) || die();
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
$groups = (new AdminDataProvider())->baselines($minimum, (int) Option::get(AdminGuard::MODULE_ID, 'baseline_window', '5'));
$APPLICATION->SetTitle((string) Loc::getMessage('GCS_BASELINES_TITLE'));
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';
?>
<table class="adm-list-table"><thead><tr><td><?= htmlspecialcharsbx((string) Loc::getMessage('GCS_SOURCE')) ?></td><td><?= htmlspecialcharsbx((string) Loc::getMessage('GCS_KIND')) ?></td><td>schema</td><td>status</td><td>samples</td><td></td></tr></thead><tbody>
<?php foreach ($groups as $group): ?><tr><td><?= htmlspecialcharsbx((string) $group['SOURCE_KEY']) ?></td><td><?= htmlspecialcharsbx((string) $group['DOCUMENT_KIND']) ?></td><td><?= (int) $group['ANALYZER_SCHEMA_VERSION'] ?></td><td><?= htmlspecialcharsbx((string) $group['STATUS']) ?></td><td><?= (int) $group['COUNT'] ?>/<?= $minimum ?><br><?= htmlspecialcharsbx(json_encode($group['MEDIANS'], JSON_UNESCAPED_UNICODE)) ?><br><?php foreach ($group['SCAN_IDS'] as $scanId): ?><a href="gorshkov_catalogsentinel_scan.php?id=<?= (int) $scanId ?>&amp;lang=<?= urlencode(LANGUAGE_ID) ?>">#<?= (int) $scanId ?></a> <?php endforeach; ?></td><td><?php if (AdminGuard::right() >= 'W'): ?><form method="post" onsubmit="return confirm('<?= CUtil::JSEscape((string) Loc::getMessage('GCS_RESET_CONFIRM')) ?>')"><?= bitrix_sessid_post() ?><input type="hidden" name="source_key" value="<?= htmlspecialcharsbx((string) $group['SOURCE_KEY']) ?>"><input type="hidden" name="document_kind" value="<?= htmlspecialcharsbx((string) $group['DOCUMENT_KIND']) ?>"><input type="hidden" name="schema_version" value="<?= (int) $group['ANALYZER_SCHEMA_VERSION'] ?>"><button name="reset" value="Y"><?= htmlspecialcharsbx((string) Loc::getMessage('GCS_RESET')) ?></button></form><?php endif; ?></td></tr><?php endforeach; ?>
</tbody></table>
<?php require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php'; ?>
