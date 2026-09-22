<?php

declare(strict_types=1);

$gorshkov_catalogsentinel_default_option = [
    'mode' => 'OBSERVE',
    'policy_version' => '1',
    'analyzer_schema_version' => '1',
    'scan_error_policy' => 'ALLOW_AND_ALERT',
    'baseline_window' => '5',
    'baseline_min_samples' => '3',
    'retention_days' => '90',
    'sample_limit' => '50',
    'decision_cache_minutes' => '30',
    'notification_enabled' => 'N',
    'notification_emails' => '',
    'notification_cooldown_minutes' => '15',
    'dry_run_max_bytes' => '52428800',
    'protected_properties_json' => '[]',
    'rules_json' => '{}',
];
