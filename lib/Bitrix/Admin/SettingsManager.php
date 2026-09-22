<?php

declare(strict_types=1);

namespace Gorshkov\CatalogSentinel\Bitrix\Admin;

use Bitrix\Main\Config\Option;
use Gorshkov\CatalogSentinel\Domain\Policy\Mode;
use Gorshkov\CatalogSentinel\Domain\Policy\PolicySnapshot;
use Gorshkov\CatalogSentinel\Domain\Policy\ScanErrorPolicy;

final class SettingsManager
{
    private const MODULE_ID = 'gorshkov.catalogsentinel';

    /** @param array<string, mixed> $input @return list<string> */
    public function save(array $input): array
    {
        $errors = [];
        $mode = strtoupper((string) ($input['mode'] ?? ''));
        $errorPolicy = strtoupper((string) ($input['scan_error_policy'] ?? ''));
        if (!Mode::isValid($mode)) {
            $errors[] = 'mode';
        }
        if (!in_array($errorPolicy, [ScanErrorPolicy::ALLOW_AND_ALERT, ScanErrorPolicy::BLOCK_AND_ALERT], true)) {
            $errors[] = 'scan_error_policy';
        }
        $integers = [
            'baseline_window' => [1, 100],
            'baseline_min_samples' => [1, 100],
            'retention_days' => [7, 3650],
            'sample_limit' => [0, 500],
            'decision_cache_minutes' => [1, 1440],
            'notification_cooldown_minutes' => [0, 1440],
            'dry_run_max_bytes' => [1024, 1_073_741_824],
        ];
        $values = [];
        foreach ($integers as $name => [$minimum, $maximum]) {
            $value = filter_var($input[$name] ?? null, FILTER_VALIDATE_INT);
            if ($value === false || $value < $minimum || $value > $maximum) {
                $errors[] = $name;
            } else {
                $values[$name] = $value;
            }
        }
        if (($values['baseline_min_samples'] ?? 1) > ($values['baseline_window'] ?? 1)) {
            $errors[] = 'baseline_min_samples';
        }
        $emails = preg_split('/[\s,;]+/', trim((string) ($input['notification_emails'] ?? '')), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        foreach ($emails as $email) {
            if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                $errors[] = 'notification_emails';
                break;
            }
        }
        $properties = preg_split('/\R+/', (string) ($input['protected_properties'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $properties = array_values(array_unique(array_map('trim', $properties)));
        foreach ($properties as $property) {
            if ($property === '' || strlen($property) > 255) {
                $errors[] = 'protected_properties';
                break;
            }
        }
        $rules = PolicySnapshot::defaultRules();
        $thresholdNames = [
            'document.item_count_drop' => ['max_ratio', 'min_baseline_objects'],
            'stock.zero_spike' => ['min_current_ratio', 'min_ratio_delta', 'min_affected', 'min_explicit'],
            'price.zero_spike' => ['min_current_ratio', 'min_ratio_delta', 'min_affected', 'min_explicit'],
            'property.empty_spike' => ['min_current_ratio', 'min_ratio_delta', 'min_affected', 'min_explicit'],
            'identifier.missing_ratio' => ['min_objects', 'min_missing', 'min_ratio'],
            'file.size_drop' => ['max_ratio', 'min_baseline_bytes'],
        ];
        foreach ($thresholdNames as $code => $names) {
            $rules[$code]['enabled'] = isset($input['rules'][$code]['enabled']);
            $action = strtoupper((string) ($input['rules'][$code]['action'] ?? $rules[$code]['action']));
            if (!in_array($action, ['WARN', 'BLOCK'], true)) {
                $errors[] = 'rules.' . $code . '.action';
            } else {
                $rules[$code]['action'] = $action;
            }
            foreach ($names as $name) {
                $raw = $input['rules'][$code][$name] ?? null;
                $numeric = filter_var($raw, FILTER_VALIDATE_FLOAT);
                if ($numeric === false || $numeric < 0 || (str_contains($name, 'ratio') && $numeric > 1)) {
                    $errors[] = 'rules.' . $code . '.' . $name;
                } else {
                    $rules[$code][$name] = str_starts_with($name, 'min_') && !str_contains($name, 'ratio')
                        ? (int) $numeric
                        : (float) $numeric;
                }
            }
        }
        if ($errors !== []) {
            return array_values(array_unique($errors));
        }

        $oldMode = Option::get(self::MODULE_ID, 'mode', Mode::OBSERVE);
        Option::set(self::MODULE_ID, 'mode', $mode);
        Option::set(self::MODULE_ID, 'scan_error_policy', $errorPolicy);
        foreach ($values as $name => $value) {
            Option::set(self::MODULE_ID, $name, (string) $value);
        }
        Option::set(self::MODULE_ID, 'notification_enabled', isset($input['notification_enabled']) ? 'Y' : 'N');
        Option::set(self::MODULE_ID, 'notification_emails', implode(',', $emails));
        Option::set(self::MODULE_ID, 'protected_properties_json', json_encode($properties, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        Option::set(self::MODULE_ID, 'rules_json', json_encode($rules, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        Option::set(self::MODULE_ID, 'policy_version', (string) ((int) Option::get(self::MODULE_ID, 'policy_version', '1') + 1));
        if ($oldMode !== $mode && class_exists('CEventLog')) {
            \CEventLog::Add([
                'SEVERITY' => 'SECURITY',
                'AUDIT_TYPE_ID' => 'GCS_MODE_CHANGED',
                'MODULE_ID' => self::MODULE_ID,
                'ITEM_ID' => '',
                'DESCRIPTION' => $oldMode . ' -> ' . $mode,
            ]);
        }

        return [];
    }
}
