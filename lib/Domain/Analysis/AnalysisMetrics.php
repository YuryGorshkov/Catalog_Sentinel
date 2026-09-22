<?php

declare(strict_types=1);

namespace Gorshkov\CatalogSentinel\Domain\Analysis;

use InvalidArgumentException;

final class AnalysisMetrics
{
    /** @var array<string, int|float> */
    private array $values;

    /** @param array<string, int|float> $values */
    public function __construct(array $values)
    {
        foreach ($values as $name => $value) {
            if ($name === '' || (!is_int($value) && !is_float($value)) || !is_finite((float) $value)) {
                throw new InvalidArgumentException('Metrics must contain finite numeric values keyed by non-empty names.');
            }
        }

        ksort($values);
        $this->values = $values;
    }

    public function has(string $name): bool
    {
        return array_key_exists($name, $this->values);
    }

    public function get(string $name): int|float|null
    {
        return $this->values[$name] ?? null;
    }

    /** @return array<string, int|float> */
    public function all(): array
    {
        return $this->values;
    }
}
