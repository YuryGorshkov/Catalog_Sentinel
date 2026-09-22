<?php

declare(strict_types=1);

namespace Gorshkov\CatalogSentinel\Domain\Policy;

use InvalidArgumentException;

final class PolicySnapshot
{
    /**
     * @param list<string> $protectedProperties
     * @param array<string, array<string, bool|int|float|string>> $rules
     */
    public function __construct(
        public readonly int $version,
        public readonly int $analyzerSchemaVersion,
        public readonly string $mode,
        public readonly string $scanErrorPolicy,
        public readonly int $baselineWindow,
        public readonly int $baselineMinSamples,
        public readonly int $retentionDays,
        public readonly int $sampleLimit,
        public readonly int $decisionCacheMinutes,
        public readonly bool $notificationEnabled,
        public readonly int $notificationCooldownMinutes,
        public readonly array $protectedProperties,
        private readonly array $rules,
    ) {
        if (!Mode::isValid($mode)) {
            throw new InvalidArgumentException('Invalid mode.');
        }
        if (!in_array($scanErrorPolicy, [ScanErrorPolicy::ALLOW_AND_ALERT, ScanErrorPolicy::BLOCK_AND_ALERT], true)) {
            throw new InvalidArgumentException('Invalid scan error policy.');
        }
        if ($baselineWindow < 1 || $baselineMinSamples < 1 || $baselineMinSamples > $baselineWindow) {
            throw new InvalidArgumentException('Invalid baseline window.');
        }
        if ($retentionDays < 7 || $retentionDays > 3650 || $sampleLimit < 0 || $sampleLimit > 500) {
            throw new InvalidArgumentException('Invalid retention or sample limit.');
        }
    }

    public static function defaults(string $mode = Mode::OBSERVE): self
    {
        return new self(
            1,
            1,
            $mode,
            ScanErrorPolicy::ALLOW_AND_ALERT,
            5,
            3,
            90,
            50,
            30,
            false,
            15,
            [],
            self::defaultRules(),
        );
    }

    /** @return array<string, bool|int|float|string> */
    public function rule(string $code): array
    {
        return $this->rules[$code] ?? ['enabled' => false];
    }

    /** @return array<string, array<string, bool|int|float|string>> */
    public function rules(): array
    {
        return $this->rules;
    }

    /** @return array<string, array<string, bool|int|float|string>> */
    public static function defaultRules(): array
    {
        return [
            'xml.well_formed' => ['enabled' => true, 'action' => 'BLOCK'],
            'document.supported' => ['enabled' => true, 'action' => 'WARN'],
            'document.item_count_drop' => [
                'enabled' => true,
                'action' => 'BLOCK',
                'max_ratio' => 0.50,
                'min_baseline_objects' => 100,
            ],
            'stock.zero_spike' => [
                'enabled' => true,
                'action' => 'BLOCK',
                'min_current_ratio' => 0.80,
                'min_ratio_delta' => 0.50,
                'min_affected' => 100,
                'min_explicit' => 100,
            ],
            'price.zero_spike' => [
                'enabled' => true,
                'action' => 'BLOCK',
                'min_current_ratio' => 0.80,
                'min_ratio_delta' => 0.50,
                'min_affected' => 100,
                'min_explicit' => 100,
            ],
            'property.empty_spike' => [
                'enabled' => false,
                'action' => 'BLOCK',
                'min_current_ratio' => 0.80,
                'min_ratio_delta' => 0.50,
                'min_affected' => 50,
                'min_explicit' => 50,
            ],
            'identifier.missing_ratio' => [
                'enabled' => true,
                'action' => 'WARN',
                'min_objects' => 100,
                'min_missing' => 10,
                'min_ratio' => 0.02,
            ],
            'file.size_drop' => [
                'enabled' => true,
                'action' => 'WARN',
                'max_ratio' => 0.30,
                'min_baseline_bytes' => 1048576,
            ],
        ];
    }
}
