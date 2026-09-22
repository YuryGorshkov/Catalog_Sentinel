<?php

declare(strict_types=1);

namespace Gorshkov\CatalogSentinel\Infrastructure\Support;

use Gorshkov\CatalogSentinel\Application\Contract\SourceKeyResolverInterface;
use Gorshkov\CatalogSentinel\Application\DTO\SourceIdentity;
use Gorshkov\CatalogSentinel\Domain\Analysis\AnalysisResult;

final class SafeSourceKeyResolver implements SourceKeyResolverInterface
{
    private const ALLOWED_PARAMETERS = ['SITE_ID', 'IBLOCK_ID', 'USE_CRC', 'USE_OFFERS'];

    public function resolve(array $parameters, AnalysisResult $analysis, ?string $label = null): SourceIdentity
    {
        $parts = ['kind' => $analysis->documentKind];
        foreach ($analysis->sourceHints as $key => $value) {
            $parts['hint.' . $key] = $value;
        }
        foreach (self::ALLOWED_PARAMETERS as $key) {
            if (isset($parameters[$key]) && is_scalar($parameters[$key])) {
                $parts['param.' . $key] = (string) $parameters[$key];
            }
        }
        ksort($parts);
        $raw = $parts === ['kind' => $analysis->documentKind] ? 'default|' . $analysis->documentKind : json_encode($parts, JSON_THROW_ON_ERROR);
        $safeLabel = trim((string) $label);
        if ($safeLabel === '') {
            $hintValues = array_values($analysis->sourceHints);
            $safeLabel = $hintValues !== [] ? (string) $hintValues[0] : 'default';
        }

        return new SourceIdentity(hash('sha256', $raw), substr($safeLabel, 0, 255));
    }
}
