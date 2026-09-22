<?php

declare(strict_types=1);

namespace Gorshkov\CatalogSentinel\Domain\Baseline;

final class BaselineSnapshot
{
    /** @param array<string, float> $medians */
    public function __construct(
        public readonly string $sourceKey,
        public readonly string $documentKind,
        public readonly int $analyzerSchemaVersion,
        public readonly int $sampleCount,
        public readonly int $minimumSamples,
        private readonly array $medians,
    ) {
    }

    public static function empty(
        string $sourceKey,
        string $documentKind,
        int $analyzerSchemaVersion,
        int $minimumSamples,
    ): self {
        return new self($sourceKey, $documentKind, $analyzerSchemaVersion, 0, $minimumSamples, []);
    }

    public function isReadyFor(int $schemaVersion): bool
    {
        return $this->sampleCount >= $this->minimumSamples
            && $this->analyzerSchemaVersion === $schemaVersion;
    }

    public function median(string $metric): ?float
    {
        return $this->medians[$metric] ?? null;
    }

    /** @return array<string, float> */
    public function medians(): array
    {
        return $this->medians;
    }

    /**
     * @param list<array<string, int|float>> $samples
     */
    public static function fromSamples(
        string $sourceKey,
        string $documentKind,
        int $schemaVersion,
        int $minimumSamples,
        int $window,
        array $samples,
    ): self {
        $samples = array_slice($samples, -$window);
        $valuesByMetric = [];
        foreach ($samples as $sample) {
            foreach ($sample as $metric => $value) {
                $valuesByMetric[$metric][] = $value;
            }
        }

        $calculator = new MedianCalculator();
        $medians = [];
        foreach ($valuesByMetric as $metric => $values) {
            $medians[$metric] = $calculator->calculate($values);
        }

        ksort($medians);

        return new self(
            $sourceKey,
            $documentKind,
            $schemaVersion,
            count($samples),
            $minimumSamples,
            $medians,
        );
    }
}
