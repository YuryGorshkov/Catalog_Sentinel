<?php

declare(strict_types=1);

namespace Gorshkov\CatalogSentinel\Infrastructure\Support;

use Gorshkov\CatalogSentinel\Application\Contract\LoggerInterface;

final class BitrixEventLogger implements LoggerInterface
{
    public function error(string $code, array $context = []): void
    {
        $this->write('ERROR', $code, $context);
    }

    public function warning(string $code, array $context = []): void
    {
        $this->write('WARNING', $code, $context);
    }

    /** @param array<string, scalar|null> $context */
    private function write(string $severity, string $code, array $context): void
    {
        if (!class_exists('CEventLog')) {
            return;
        }
        \CEventLog::Add([
            'SEVERITY' => $severity,
            'AUDIT_TYPE_ID' => 'GCS_' . strtoupper(str_replace('.', '_', $code)),
            'MODULE_ID' => 'gorshkov.catalogsentinel',
            'ITEM_ID' => '',
            'DESCRIPTION' => json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }
}
