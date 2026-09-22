<?php

declare(strict_types=1);

namespace Gorshkov\CatalogSentinel\Infrastructure\Xml;

final class SampleCollector
{
    /** @var array<string, list<string>> */
    private array $samples = [];

    public function __construct(
        private readonly int $limit,
        private readonly int $maxLength = 200,
    ) {
    }

    public function add(string $category, string $value): void
    {
        if ($this->limit === 0 || count($this->samples[$category] ?? []) >= $this->limit) {
            return;
        }
        $value = trim($value);
        if ($value === '') {
            return;
        }
        if (function_exists('mb_substr')) {
            $value = mb_substr($value, 0, $this->maxLength, 'UTF-8');
        } else {
            $value = substr($value, 0, $this->maxLength);
        }
        $this->samples[$category][] = $value;
    }

    /** @return array<string, list<string>> */
    public function all(): array
    {
        return $this->samples;
    }
}
