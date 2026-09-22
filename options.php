<?php

declare(strict_types=1);

use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Mail\Event;
use Gorshkov\CatalogSentinel\Bitrix\Admin\AdminGuard;
use Gorshkov\CatalogSentinel\Bitrix\Admin\SettingsManager;
use Gorshkov\CatalogSentinel\Bitrix\Admin\Diagnostics;
use Gorshkov\CatalogSentinel\Domain\Policy\PolicySnapshot;

defined('B_PROLOG_INCLUDED') || die();
Loader::includeModule(AdminGuard::MODULE_ID) || die();
Loc::loadMessages(__FILE__);
AdminGuard::requireRead();
$errors = [];
$saved = false;
$testMailSent = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_mail'])) {
    try {
        AdminGuard::requirePost();
        $emailTo = Option::get(AdminGuard::MODULE_ID, 'notification_emails', '');
        if (trim($emailTo) === '') {
            $errors[] = 'notification_emails';
        } else {
            Event::send([
                'EVENT_NAME' => 'GCS_CATALOG_SENTINEL_BLOCKED',
                'LID' => defined('SITE_ID') && SITE_ID !== '' ? SITE_ID : 's1',
                'C_FIELDS' => [
                    'EMAIL_TO' => $emailTo,
                    'SCAN_ID' => 'TEST',
                    'FILE_NAME' => 'test.xml',
                    'DOCUMENT_KIND' => 'OFFERS',
                    'RULE_CODE' => 'test.notification',
                    'ACTUAL' => '{}',
                    'THRESHOLDS' => '{}',
                ],
            ]);
            $testMailSent = true;
        }
    } catch (Throwable $exception) {
        $errors[] = $exception->getMessage();
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    try {
        AdminGuard::requirePost();
        $errors = (new SettingsManager())->save($_POST);
        $saved = $errors === [];
    } catch (Throwable $exception) {
        $errors[] = $exception->getMessage();
    }
}
$rules = json_decode(Option::get(AdminGuard::MODULE_ID, 'rules_json', '{}'), true) ?: PolicySnapshot::defaultRules();
$defaults = PolicySnapshot::defaultRules();
$rules = array_replace_recursive($defaults, $rules);
$properties = implode("\n", json_decode(Option::get(AdminGuard::MODULE_ID, 'protected_properties_json', '[]'), true) ?: []);
if ($saved) {
    CAdminMessage::ShowNote((string) Loc::getMessage('GCS_SETTINGS_SAVED'));
}
if ($testMailSent) {
    CAdminMessage::ShowNote((string) Loc::getMessage('GCS_TEST_MAIL_SENT'));
}
if ($errors !== []) {
    CAdminMessage::ShowMessage((string) Loc::getMessage('GCS_SETTINGS_ERRORS') . ': ' . implode(', ', array_map('htmlspecialcharsbx', $errors)));
}
?>
<div class="adm-info-message"><?= htmlspecialcharsbx((string) Loc::getMessage('GCS_FILE_SCOPE_NOTICE')) ?></div>
<form method="post"><?= bitrix_sessid_post() ?>
<table class="adm-detail-content-table edit-table"><tbody>
<tr><td>mode</td><td><select name="mode"><?php foreach (['DISABLED', 'OBSERVE', 'PROTECT'] as $mode): ?><option<?= Option::get(AdminGuard::MODULE_ID, 'mode', 'OBSERVE') === $mode ? ' selected' : '' ?>><?= $mode ?></option><?php endforeach; ?></select></td></tr>
<tr><td>scan_error_policy</td><td><select name="scan_error_policy"><?php foreach (['ALLOW_AND_ALERT', 'BLOCK_AND_ALERT'] as $policy): ?><option<?= Option::get(AdminGuard::MODULE_ID, 'scan_error_policy', 'ALLOW_AND_ALERT') === $policy ? ' selected' : '' ?>><?= $policy ?></option><?php endforeach; ?></select></td></tr>
<?php foreach (['baseline_window', 'baseline_min_samples', 'retention_days', 'sample_limit', 'decision_cache_minutes', 'notification_cooldown_minutes', 'dry_run_max_bytes'] as $name): ?><tr><td><?= htmlspecialcharsbx($name) ?></td><td><input type="number" name="<?= htmlspecialcharsbx($name) ?>" value="<?= (int) Option::get(AdminGuard::MODULE_ID, $name, '0') ?>"></td></tr><?php endforeach; ?>
<tr><td>notification_enabled</td><td><input type="checkbox" name="notification_enabled" value="Y"<?= Option::get(AdminGuard::MODULE_ID, 'notification_enabled', 'N') === 'Y' ? ' checked' : '' ?>></td></tr>
<tr><td>notification_emails</td><td><input type="text" name="notification_emails" value="<?= htmlspecialcharsbx(Option::get(AdminGuard::MODULE_ID, 'notification_emails', '')) ?>"></td></tr>
<tr><td>protected_properties</td><td><textarea name="protected_properties" rows="5" cols="50"><?= htmlspecialcharsbx($properties) ?></textarea></td></tr>
</tbody></table>
<h2><?= htmlspecialcharsbx((string) Loc::getMessage('GCS_RULES')) ?></h2>
<?php foreach ($rules as $code => $config): ?><fieldset><legend><?= htmlspecialcharsbx($code) ?></legend>
<?php if ($code === 'xml.well_formed'): ?><input type="hidden" name="rules[<?= htmlspecialcharsbx($code) ?>][enabled]" value="Y"><p><?= htmlspecialcharsbx((string) Loc::getMessage('GCS_REQUIRED_RULE')) ?></p><?php else: ?><label><input type="checkbox" name="rules[<?= htmlspecialcharsbx($code) ?>][enabled]" value="Y"<?= !empty($config['enabled']) ? ' checked' : '' ?>> enabled</label><?php endif; ?>
<?php foreach ($config as $name => $value): ?><?php if (!in_array($name, ['enabled'], true)): ?><label><?= htmlspecialcharsbx($name) ?> <input type="text" name="rules[<?= htmlspecialcharsbx($code) ?>][<?= htmlspecialcharsbx($name) ?>]" value="<?= htmlspecialcharsbx((string) $value) ?>"></label><?php endif; ?><?php endforeach; ?></fieldset><?php endforeach; ?>
<?php if (AdminGuard::right() >= 'W'): ?><input type="submit" name="save" value="<?= htmlspecialcharsbx((string) Loc::getMessage('GCS_SAVE')) ?>"> <input type="submit" name="test_mail" value="<?= htmlspecialcharsbx((string) Loc::getMessage('GCS_TEST_MAIL')) ?>"><?php endif; ?>
</form>
<h2><?= htmlspecialcharsbx((string) Loc::getMessage('GCS_DIAGNOSTICS')) ?></h2>
<table class="adm-list-table"><tbody><?php foreach ((new Diagnostics())->collect() as $name => $value): ?><tr><td><?= htmlspecialcharsbx($name) ?></td><td><?= htmlspecialcharsbx($value) ?></td></tr><?php endforeach; ?></tbody></table>
