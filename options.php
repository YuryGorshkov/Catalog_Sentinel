<?php

declare(strict_types=1);

use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Mail\Event;
use Gorshkov\CatalogSentinel\Bitrix\Admin\AdminGuard;
use Gorshkov\CatalogSentinel\Bitrix\Admin\Diagnostics;
use Gorshkov\CatalogSentinel\Bitrix\Admin\SettingsManager;
use Gorshkov\CatalogSentinel\Domain\Policy\PolicySnapshot;

defined('B_PROLOG_INCLUDED') || die();
Loader::includeModule('gorshkov.catalogsentinel') || die();
Loc::loadMessages(__FILE__);
AdminGuard::requireRead();

$integerSettings = [
    'baseline_window' => ['default' => '5', 'label' => 'GCS_FIELD_BASELINE_WINDOW', 'hint' => 'GCS_HINT_BASELINE_WINDOW'],
    'baseline_min_samples' => ['default' => '3', 'label' => 'GCS_FIELD_BASELINE_MIN', 'hint' => 'GCS_HINT_BASELINE_MIN'],
    'retention_days' => ['default' => '90', 'label' => 'GCS_FIELD_RETENTION', 'hint' => 'GCS_HINT_RETENTION'],
    'sample_limit' => ['default' => '50', 'label' => 'GCS_FIELD_SAMPLE_LIMIT', 'hint' => 'GCS_HINT_SAMPLE_LIMIT'],
    'decision_cache_minutes' => ['default' => '30', 'label' => 'GCS_FIELD_CACHE', 'hint' => 'GCS_HINT_CACHE'],
    'notification_cooldown_minutes' => ['default' => '15', 'label' => 'GCS_FIELD_NOTIFICATION_COOLDOWN', 'hint' => 'GCS_HINT_NOTIFICATION_COOLDOWN'],
    'dry_run_max_bytes' => ['default' => '52428800', 'label' => 'GCS_FIELD_DRY_RUN_BYTES', 'hint' => 'GCS_HINT_DRY_RUN_BYTES'],
];
$ruleMeta = [
    'xml.well_formed' => ['label' => 'GCS_RULE_XML_WELL_FORMED', 'hint' => 'GCS_RULE_HINT_XML_WELL_FORMED'],
    'document.supported' => ['label' => 'GCS_RULE_DOCUMENT_SUPPORTED', 'hint' => 'GCS_RULE_HINT_DOCUMENT_SUPPORTED'],
    'document.item_count_drop' => ['label' => 'GCS_RULE_ITEM_COUNT_DROP', 'hint' => 'GCS_RULE_HINT_ITEM_COUNT_DROP'],
    'stock.zero_spike' => ['label' => 'GCS_RULE_STOCK_ZERO_SPIKE', 'hint' => 'GCS_RULE_HINT_STOCK_ZERO_SPIKE'],
    'price.zero_spike' => ['label' => 'GCS_RULE_PRICE_ZERO_SPIKE', 'hint' => 'GCS_RULE_HINT_PRICE_ZERO_SPIKE'],
    'property.empty_spike' => ['label' => 'GCS_RULE_PROPERTY_EMPTY_SPIKE', 'hint' => 'GCS_RULE_HINT_PROPERTY_EMPTY_SPIKE'],
    'identifier.missing_ratio' => ['label' => 'GCS_RULE_IDENTIFIER_MISSING', 'hint' => 'GCS_RULE_HINT_IDENTIFIER_MISSING'],
    'file.size_drop' => ['label' => 'GCS_RULE_FILE_SIZE_DROP', 'hint' => 'GCS_RULE_HINT_FILE_SIZE_DROP'],
];
$thresholdMeta = [
    'max_ratio' => ['label' => 'GCS_THRESHOLD_MAX_RATIO', 'hint' => 'GCS_THRESHOLD_HINT_MAX_RATIO'],
    'min_baseline_objects' => ['label' => 'GCS_THRESHOLD_MIN_BASELINE_OBJECTS', 'hint' => 'GCS_THRESHOLD_HINT_MIN_BASELINE_OBJECTS'],
    'min_current_ratio' => ['label' => 'GCS_THRESHOLD_MIN_CURRENT_RATIO', 'hint' => 'GCS_THRESHOLD_HINT_MIN_CURRENT_RATIO'],
    'min_ratio_delta' => ['label' => 'GCS_THRESHOLD_MIN_RATIO_DELTA', 'hint' => 'GCS_THRESHOLD_HINT_MIN_RATIO_DELTA'],
    'min_affected' => ['label' => 'GCS_THRESHOLD_MIN_AFFECTED', 'hint' => 'GCS_THRESHOLD_HINT_MIN_AFFECTED'],
    'min_explicit' => ['label' => 'GCS_THRESHOLD_MIN_EXPLICIT', 'hint' => 'GCS_THRESHOLD_HINT_MIN_EXPLICIT'],
    'min_objects' => ['label' => 'GCS_THRESHOLD_MIN_OBJECTS', 'hint' => 'GCS_THRESHOLD_HINT_MIN_OBJECTS'],
    'min_missing' => ['label' => 'GCS_THRESHOLD_MIN_MISSING', 'hint' => 'GCS_THRESHOLD_HINT_MIN_MISSING'],
    'min_ratio' => ['label' => 'GCS_THRESHOLD_MIN_RATIO', 'hint' => 'GCS_THRESHOLD_HINT_MIN_RATIO'],
    'min_baseline_bytes' => ['label' => 'GCS_THRESHOLD_MIN_BASELINE_BYTES', 'hint' => 'GCS_THRESHOLD_HINT_MIN_BASELINE_BYTES'],
];

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
$rules = array_replace_recursive(PolicySnapshot::defaultRules(), $rules);
$properties = implode("\n", json_decode(Option::get(AdminGuard::MODULE_ID, 'protected_properties_json', '[]'), true) ?: []);
$mode = Option::get(AdminGuard::MODULE_ID, 'mode', 'OBSERVE');
$errorPolicy = Option::get(AdminGuard::MODULE_ID, 'scan_error_policy', 'ALLOW_AND_ALERT');
$diagnosticLabels = [
    'php' => (string) Loc::getMessage('GCS_DIAG_PHP'),
    'bitrix' => (string) Loc::getMessage('GCS_DIAG_BITRIX'),
    'module' => (string) Loc::getMessage('GCS_DIAG_MODULE'),
    'xmlreader' => (string) Loc::getMessage('GCS_DIAG_XMLREADER'),
    'tables' => (string) Loc::getMessage('GCS_DIAG_TABLES'),
    'before_handler' => (string) Loc::getMessage('GCS_DIAG_BEFORE_HANDLER'),
    'success_handler' => (string) Loc::getMessage('GCS_DIAG_SUCCESS_HANDLER'),
    'cleanup_agent' => (string) Loc::getMessage('GCS_DIAG_CLEANUP_AGENT'),
    'event_log' => (string) Loc::getMessage('GCS_DIAG_EVENT_LOG'),
];

if ($saved) {
    CAdminMessage::ShowNote((string) Loc::getMessage('GCS_SETTINGS_SAVED'));
}
if ($testMailSent) {
    CAdminMessage::ShowNote((string) Loc::getMessage('GCS_TEST_MAIL_SENT'));
}
if ($errors !== []) {
    CAdminMessage::ShowMessage(
        (string) Loc::getMessage('GCS_SETTINGS_ERRORS') . ': ' . implode(', ', array_map('htmlspecialcharsbx', $errors)),
    );
}
?>
<style>
.gcs-settings{max-width:1180px;margin-bottom:48px;color:#263238;font-size:14px}.gcs-settings .adm-info-message{max-width:none}.gcs-settings-section{margin-top:16px;padding:20px;border:1px solid #d7e0e4;border-radius:10px;background:#fff;box-shadow:0 2px 7px rgba(0,0,0,.04)}.gcs-settings-section h2{margin:0 0 5px;font-size:20px;line-height:1.3}.gcs-section-hint{margin:0 0 18px;color:#687780;line-height:1.45}.gcs-settings-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px 24px}.gcs-field{min-width:0}.gcs-field-wide{grid-column:1/-1}.gcs-label,.gcs-rule-title{display:flex;align-items:center;gap:5px;min-height:22px;margin-bottom:7px;font-size:14px;line-height:1.3;font-weight:600}.gcs-rule-title{margin:0;font-size:16px}.gcs-label .adm-hint,.gcs-rule-title .adm-hint{flex:0 0 auto;margin:0}.gcs-settings input[type=text],.gcs-settings input[type=number],.gcs-settings select,.gcs-settings textarea{box-sizing:border-box;width:100%;min-height:38px;padding:7px 10px;font-size:14px}.gcs-settings textarea{min-height:88px;resize:vertical}.gcs-check{display:flex;gap:8px;align-items:center;min-height:38px}.gcs-check input{margin:0}.gcs-check-text{display:flex;align-items:center;gap:5px;font-weight:600}.gcs-rules{display:grid;gap:12px}.gcs-rule{padding:17px;border:1px solid #dce3e7;border-radius:8px;background:#fbfcfd}.gcs-rule-head{display:flex;justify-content:space-between;gap:18px;align-items:center}.gcs-rule-controls{display:flex;gap:16px;align-items:center;flex-wrap:wrap}.gcs-rule-controls label{display:flex;gap:7px;align-items:center;white-space:nowrap}.gcs-rule-controls select{width:auto;min-width:170px}.gcs-thresholds{display:grid;grid-template-columns:repeat(4,minmax(150px,1fr));gap:14px 16px;margin-top:15px}.gcs-fixed{display:inline-block;padding:6px 10px;border-radius:999px;background:#edf3f6;color:#40545e;font-size:12px}.gcs-actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:20px}.gcs-primary,.gcs-secondary{min-height:38px;padding:0 18px;border-radius:5px;font-weight:bold;cursor:pointer}.gcs-primary{border:0;background:#315b72;color:#fff}.gcs-secondary{border:1px solid #9fb0b9;background:#fff;color:#315b72}.gcs-diagnostics{width:100%;border-collapse:collapse}.gcs-diagnostics th,.gcs-diagnostics td{padding:10px 12px;border-bottom:1px solid #e4eaed;text-align:left}.gcs-diagnostics th{width:45%;font-weight:normal;color:#53646d}@media(max-width:900px){.gcs-thresholds{grid-template-columns:repeat(2,minmax(150px,1fr))}}@media(max-width:680px){.gcs-settings-grid,.gcs-thresholds{grid-template-columns:1fr}.gcs-field-wide{grid-column:auto}.gcs-rule-head{display:block}.gcs-rule-controls{margin-top:12px}}
.gcs-thresholds .gcs-field{display:flex;flex-direction:column}.gcs-thresholds .gcs-field>input{margin-top:auto}
</style>

<div class="gcs-settings">
    <div class="adm-info-message"><?= htmlspecialcharsbx((string) Loc::getMessage('GCS_FILE_SCOPE_NOTICE')) ?></div>
    <form method="post">
        <?= bitrix_sessid_post() ?>

        <section class="gcs-settings-section">
            <h2><?= htmlspecialcharsbx((string) Loc::getMessage('GCS_SECTION_BEHAVIOR')) ?></h2>
            <p class="gcs-section-hint"><?= htmlspecialcharsbx((string) Loc::getMessage('GCS_SECTION_BEHAVIOR_HINT')) ?></p>
            <div class="gcs-settings-grid">
                <div class="gcs-field">
                    <label class="gcs-label" for="gcs-mode"><?= htmlspecialcharsbx((string) Loc::getMessage('GCS_FIELD_MODE')) ?><?php ShowJSHint((string) Loc::getMessage('GCS_HINT_MODE')); ?></label>
                    <select id="gcs-mode" name="mode">
                        <?php foreach (['DISABLED', 'OBSERVE', 'PROTECT'] as $value): ?>
                            <option value="<?= htmlspecialcharsbx($value) ?>"<?= $mode === $value ? ' selected' : '' ?>><?= htmlspecialcharsbx((string) Loc::getMessage('GCS_MODE_' . $value)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="gcs-field">
                    <label class="gcs-label" for="gcs-error-policy"><?= htmlspecialcharsbx((string) Loc::getMessage('GCS_FIELD_ERROR_POLICY')) ?><?php ShowJSHint((string) Loc::getMessage('GCS_HINT_ERROR_POLICY')); ?></label>
                    <select id="gcs-error-policy" name="scan_error_policy">
                        <?php foreach (['ALLOW_AND_ALERT', 'BLOCK_AND_ALERT'] as $value): ?>
                            <option value="<?= htmlspecialcharsbx($value) ?>"<?= $errorPolicy === $value ? ' selected' : '' ?>><?= htmlspecialcharsbx((string) Loc::getMessage('GCS_POLICY_' . $value)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </section>

        <section class="gcs-settings-section">
            <h2><?= htmlspecialcharsbx((string) Loc::getMessage('GCS_SECTION_BASELINE')) ?></h2>
            <p class="gcs-section-hint"><?= htmlspecialcharsbx((string) Loc::getMessage('GCS_SECTION_BASELINE_HINT')) ?></p>
            <div class="gcs-settings-grid">
                <?php foreach (['baseline_window', 'baseline_min_samples', 'retention_days', 'decision_cache_minutes'] as $name): ?>
                    <?php $meta = $integerSettings[$name]; ?>
                    <div class="gcs-field">
                        <label class="gcs-label" for="gcs-<?= htmlspecialcharsbx($name) ?>"><?= htmlspecialcharsbx((string) Loc::getMessage($meta['label'])) ?><?php ShowJSHint((string) Loc::getMessage($meta['hint'])); ?></label>
                        <input id="gcs-<?= htmlspecialcharsbx($name) ?>" type="number" name="<?= htmlspecialcharsbx($name) ?>" value="<?= (int) Option::get(AdminGuard::MODULE_ID, $name, $meta['default']) ?>">
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="gcs-settings-section">
            <h2><?= htmlspecialcharsbx((string) Loc::getMessage('GCS_SECTION_REPORTS')) ?></h2>
            <p class="gcs-section-hint"><?= htmlspecialcharsbx((string) Loc::getMessage('GCS_SECTION_REPORTS_HINT')) ?></p>
            <div class="gcs-settings-grid">
                <?php foreach (['sample_limit', 'dry_run_max_bytes'] as $name): ?>
                    <?php $meta = $integerSettings[$name]; ?>
                    <div class="gcs-field">
                        <label class="gcs-label" for="gcs-<?= htmlspecialcharsbx($name) ?>"><?= htmlspecialcharsbx((string) Loc::getMessage($meta['label'])) ?><?php ShowJSHint((string) Loc::getMessage($meta['hint'])); ?></label>
                        <input id="gcs-<?= htmlspecialcharsbx($name) ?>" type="number" name="<?= htmlspecialcharsbx($name) ?>" value="<?= (int) Option::get(AdminGuard::MODULE_ID, $name, $meta['default']) ?>">
                    </div>
                <?php endforeach; ?>
                <div class="gcs-field gcs-field-wide">
                    <label class="gcs-label" for="gcs-properties"><?= htmlspecialcharsbx((string) Loc::getMessage('GCS_FIELD_PROPERTIES')) ?><?php ShowJSHint((string) Loc::getMessage('GCS_HINT_PROPERTIES')); ?></label>
                    <textarea id="gcs-properties" name="protected_properties"><?= htmlspecialcharsbx($properties) ?></textarea>
                </div>
            </div>
        </section>

        <section class="gcs-settings-section">
            <h2><?= htmlspecialcharsbx((string) Loc::getMessage('GCS_SECTION_NOTIFICATIONS')) ?></h2>
            <p class="gcs-section-hint"><?= htmlspecialcharsbx((string) Loc::getMessage('GCS_SECTION_NOTIFICATIONS_HINT')) ?></p>
            <div class="gcs-settings-grid">
                <div class="gcs-field gcs-field-wide">
                    <label class="gcs-check"><input type="checkbox" name="notification_enabled" value="Y"<?= Option::get(AdminGuard::MODULE_ID, 'notification_enabled', 'N') === 'Y' ? ' checked' : '' ?>><span class="gcs-check-text"><?= htmlspecialcharsbx((string) Loc::getMessage('GCS_FIELD_NOTIFICATIONS_ENABLED')) ?><?php ShowJSHint((string) Loc::getMessage('GCS_HINT_NOTIFICATIONS_ENABLED')); ?></span></label>
                </div>
                <div class="gcs-field">
                    <label class="gcs-label" for="gcs-emails"><?= htmlspecialcharsbx((string) Loc::getMessage('GCS_FIELD_EMAILS')) ?><?php ShowJSHint((string) Loc::getMessage('GCS_HINT_EMAILS')); ?></label>
                    <input id="gcs-emails" type="text" name="notification_emails" value="<?= htmlspecialcharsbx(Option::get(AdminGuard::MODULE_ID, 'notification_emails', '')) ?>">
                </div>
                <?php $cooldown = $integerSettings['notification_cooldown_minutes']; ?>
                <div class="gcs-field">
                    <label class="gcs-label" for="gcs-notification-cooldown"><?= htmlspecialcharsbx((string) Loc::getMessage($cooldown['label'])) ?><?php ShowJSHint((string) Loc::getMessage($cooldown['hint'])); ?></label>
                    <input id="gcs-notification-cooldown" type="number" name="notification_cooldown_minutes" value="<?= (int) Option::get(AdminGuard::MODULE_ID, 'notification_cooldown_minutes', $cooldown['default']) ?>">
                </div>
            </div>
        </section>

        <section class="gcs-settings-section">
            <h2><?= htmlspecialcharsbx((string) Loc::getMessage('GCS_RULES')) ?></h2>
            <p class="gcs-section-hint"><?= htmlspecialcharsbx((string) Loc::getMessage('GCS_RULES_HINT')) ?></p>
            <div class="gcs-rules">
                <?php foreach ($rules as $code => $config): ?>
                    <?php $meta = $ruleMeta[$code]; ?>
                    <div class="gcs-rule">
                        <div class="gcs-rule-head">
                            <div class="gcs-rule-title"><?= htmlspecialcharsbx((string) Loc::getMessage($meta['label'])) ?><?php ShowJSHint((string) Loc::getMessage($meta['hint'])); ?></div>
                            <div class="gcs-rule-controls">
                                <?php if ($code === 'xml.well_formed'): ?>
                                    <input type="hidden" name="rules[<?= htmlspecialcharsbx($code) ?>][enabled]" value="Y">
                                    <input type="hidden" name="rules[<?= htmlspecialcharsbx($code) ?>][action]" value="BLOCK">
                                    <span class="gcs-fixed"><?= htmlspecialcharsbx((string) Loc::getMessage('GCS_ALWAYS_ENABLED')) ?></span>
                                    <span class="gcs-fixed"><?= htmlspecialcharsbx((string) Loc::getMessage('GCS_ACTION_BLOCK')) ?></span>
                                <?php else: ?>
                                    <label><input type="checkbox" name="rules[<?= htmlspecialcharsbx($code) ?>][enabled]" value="Y"<?= !empty($config['enabled']) ? ' checked' : '' ?>> <?= htmlspecialcharsbx((string) Loc::getMessage('GCS_ENABLED')) ?></label>
                                    <label><?= htmlspecialcharsbx((string) Loc::getMessage('GCS_ACTION')) ?> <select name="rules[<?= htmlspecialcharsbx($code) ?>][action]"><?php foreach (['WARN', 'BLOCK'] as $action): ?><option value="<?= $action ?>"<?= ($config['action'] ?? '') === $action ? ' selected' : '' ?>><?= htmlspecialcharsbx((string) Loc::getMessage('GCS_ACTION_' . $action)) ?></option><?php endforeach; ?></select></label>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php $thresholds = array_diff_key($config, ['enabled' => true, 'action' => true]); ?>
                        <?php if ($thresholds !== []): ?>
                            <div class="gcs-thresholds">
                                <?php foreach ($thresholds as $name => $value): ?>
                                    <?php $threshold = $thresholdMeta[$name]; ?>
                                    <div class="gcs-field"><label class="gcs-label"><?= htmlspecialcharsbx((string) Loc::getMessage($threshold['label'])) ?><?php ShowJSHint((string) Loc::getMessage($threshold['hint'])); ?></label><input type="number" step="<?= str_contains($name, 'ratio') ? '0.01' : '1' ?>" min="0"<?= str_contains($name, 'ratio') ? ' max="1"' : '' ?> name="rules[<?= htmlspecialcharsbx($code) ?>][<?= htmlspecialcharsbx($name) ?>]" value="<?= htmlspecialcharsbx((string) $value) ?>"></div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <?php if (AdminGuard::right() >= 'W'): ?>
            <div class="gcs-actions"><button class="gcs-primary" type="submit" name="save" value="Y"><?= htmlspecialcharsbx((string) Loc::getMessage('GCS_SAVE')) ?></button><button class="gcs-secondary" type="submit" name="test_mail" value="Y"><?= htmlspecialcharsbx((string) Loc::getMessage('GCS_TEST_MAIL')) ?></button></div>
        <?php endif; ?>
    </form>

    <section class="gcs-settings-section">
        <h2><?= htmlspecialcharsbx((string) Loc::getMessage('GCS_DIAGNOSTICS')) ?></h2>
        <p class="gcs-section-hint"><?= htmlspecialcharsbx((string) Loc::getMessage('GCS_DIAGNOSTICS_HINT')) ?></p>
        <table class="gcs-diagnostics"><tbody><?php foreach ((new Diagnostics())->collect() as $name => $value): ?><tr><th><?= htmlspecialcharsbx($diagnosticLabels[$name] ?? $name) ?></th><td><?= htmlspecialcharsbx($value) ?></td></tr><?php endforeach; ?></tbody></table>
    </section>
</div>
