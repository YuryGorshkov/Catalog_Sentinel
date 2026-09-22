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
Loader::includeModule(AdminGuard::MODULE_ID) || die();
Loc::loadMessages(__FILE__);
AdminGuard::requireRead();
$id = max(0, (int) ($_REQUEST['id'] ?? 0));
$provider = new AdminDataProvider();
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accept_baseline'])) {
    AdminGuard::requirePost();
    $accepted = (new AcceptBaselineSample(new BitrixScanRepository(), new BitrixBaselineRepository()))->execute($id, (int) $USER->GetID());
    $message = $accepted ? 'accepted' : 'not_accepted';
}
$scan = $provider->scan($id);
if ($scan === null) {
    CAdminMessage::ShowMessage((string) Loc::getMessage('GCS_NOT_FOUND'));
}
$APPLICATION->SetTitle((string) Loc::getMessage('GCS_SCAN_TITLE') . ' #' . $id);
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';
?>
<?php if ($message !== ''): ?><div class="adm-info-message"><?= htmlspecialcharsbx($message) ?></div><?php endif; ?>
<?php if ($scan !== null): ?>
<div class="adm-info-message"><?= htmlspecialcharsbx((string) Loc::getMessage('GCS_FILE_SCOPE_NOTICE')) ?></div>
<?php if ($scan->mode === 'OBSERVE' && $scan->decision === 'BLOCK'): ?><div class="adm-info-message adm-info-message-red"><?= htmlspecialcharsbx((string) Loc::getMessage('GCS_WOULD_BLOCK')) ?></div><?php endif; ?>
<table class="adm-detail-content-table edit-table"><tbody>
<?php foreach (['ID' => $scan->id, 'STATUS' => $scan->status, 'DECISION' => $scan->decision, 'MODE' => $scan->mode, 'DOCUMENT_KIND' => $scan->documentKind, 'FILE_NAME' => $scan->file->fileName, 'FILE_SIZE' => $scan->file->size, 'SOURCE_LABEL' => $scan->sourceLabel, 'DURATION_MS' => $scan->durationMs, 'MEMORY_DELTA_BYTES' => $scan->memoryDeltaBytes] as $label => $value): ?>
<tr><td><?= htmlspecialcharsbx($label) ?></td><td><?= htmlspecialcharsbx((string) $value) ?></td></tr>
<?php endforeach; ?>
</tbody></table>
<h2><?= htmlspecialcharsbx((string) Loc::getMessage('GCS_METRICS')) ?></h2>
<table class="adm-list-table"><tbody><?php foreach ($scan->metrics as $name => $value): ?><tr><td><?= htmlspecialcharsbx($name) ?></td><td><?= htmlspecialcharsbx((string) $value) ?></td></tr><?php endforeach; ?></tbody></table>
<h2><?= htmlspecialcharsbx((string) Loc::getMessage('GCS_RULES')) ?></h2>
<table class="adm-list-table"><thead><tr><td>rule</td><td>outcome</td><td>actual</td><td>baseline</td><td>thresholds</td></tr></thead><tbody>
<?php foreach ($scan->ruleResults as $rule): ?><tr><td><?= htmlspecialcharsbx($rule->ruleCode) ?></td><td><?= htmlspecialcharsbx($rule->outcome) ?></td><td><?= htmlspecialcharsbx(json_encode($rule->actual, JSON_UNESCAPED_UNICODE)) ?></td><td><?= htmlspecialcharsbx(json_encode($rule->baseline, JSON_UNESCAPED_UNICODE)) ?></td><td><?= htmlspecialcharsbx(json_encode($rule->thresholds, JSON_UNESCAPED_UNICODE)) ?></td></tr><?php endforeach; ?>
</tbody></table>
<h2><?= htmlspecialcharsbx((string) Loc::getMessage('GCS_SAMPLES')) ?></h2>
<table class="adm-list-table"><tbody><?php foreach ($scan->samples as $category => $ids): ?><tr><td><?= htmlspecialcharsbx($category) ?></td><td><?= htmlspecialcharsbx(implode(', ', $ids)) ?></td></tr><?php endforeach; ?></tbody></table>
<h2><?= htmlspecialcharsbx((string) Loc::getMessage('GCS_PARSER_NOTICES')) ?></h2>
<p><?= htmlspecialcharsbx(implode(', ', $scan->warnings)) ?><?= $scan->errorCode !== null ? ' — ' . htmlspecialcharsbx($scan->errorCode) : '' ?></p>
<p><a href="gorshkov_catalogsentinel_export.php?id=<?= $id ?>&amp;format=json&amp;lang=<?= urlencode(LANGUAGE_ID) ?>">JSON</a> | <a href="gorshkov_catalogsentinel_export.php?id=<?= $id ?>&amp;format=csv&amp;lang=<?= urlencode(LANGUAGE_ID) ?>">CSV</a></p>
<?php if ($scan->dryRun && $scan->decision !== 'BLOCK' && AdminGuard::right() >= 'W'): ?><form method="post"><?= bitrix_sessid_post() ?><input type="hidden" name="id" value="<?= $id ?>"><button name="accept_baseline" value="Y"><?= htmlspecialcharsbx((string) Loc::getMessage('GCS_ACCEPT_BASELINE')) ?></button></form><?php endif; ?>
<?php endif; ?>
<?php require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php'; ?>
