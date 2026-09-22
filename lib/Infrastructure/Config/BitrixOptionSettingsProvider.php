<?php

declare(strict_types=1);

namespace Gorshkov\CatalogSentinel\Infrastructure\Config;

use Bitrix\Main\Config\Option;
use Gorshkov\CatalogSentinel\Application\Contract\SettingsProviderInterface;
use Gorshkov\CatalogSentinel\Domain\Policy\Mode;
use Gorshkov\CatalogSentinel\Domain\Policy\PolicySnapshot;
use Gorshkov\CatalogSentinel\Domain\Policy\ScanErrorPolicy;

final class BitrixOptionSettingsProvider implements SettingsProviderInterface
{
    public const MODULE_ID = 'gorshkov.catalogsentinel';

    public function current(): PolicySnapshot
    {
        $defaults = PolicySnapshot::defaultRules();
        $storedRules = $this->decodeArray(Option::get(self::MODULE_ID, 'rules_json', '{}'));
        foreach ($defaults as $code => $config) {
            if (isset($storedRules[$code]) && is_array($storedRules[$code])) {
                $defaults[$code] = array_replace($config, $storedRules[$code]);
            }
        }
        $properties = array_values(array_filter(
            $this->decodeArray(Option::get(self::MODULE_ID, 'protected_properties_json', '[]')),
            static fn (mixed $value): bool => is_string($value) && trim($value) !== '',
        ));

        return new PolicySnapshot(
            max(1, (int) Option::get(self::MODULE_ID, 'policy_version', '1')),
            max(1, (int) Option::get(self::MODULE_ID, 'analyzer_schema_version', '1')),
            Option::get(self::MODULE_ID, 'mode', Mode::OBSERVE),
            Option::get(self::MODULE_ID, 'scan_error_policy', ScanErrorPolicy::ALLOW_AND_ALERT),
            (int) Option::get(self::MODULE_ID, 'baseline_window', '5'),
            (int) Option::get(self::MODULE_ID, 'baseline_min_samples', '3'),
            (int) Option::get(self::MODULE_ID, 'retention_days', '90'),
            (int) Option::get(self::MODULE_ID, 'sample_limit', '50'),
            (int) Option::get(self::MODULE_ID, 'decision_cache_minutes', '30'),
            Option::get(self::MODULE_ID, 'notification_enabled', 'N') === 'Y',
            (int) Option::get(self::MODULE_ID, 'notification_cooldown_minutes', '15'),
            $properties,
            $defaults,
        );
    }

    /** @return array<mixed> */
    private function decodeArray(string $json): array
    {
        try {
            $decoded = json_decode($json, true, 64, JSON_THROW_ON_ERROR);

            return is_array($decoded) ? $decoded : [];
        } catch (\JsonException) {
            return [];
        }
    }
}
