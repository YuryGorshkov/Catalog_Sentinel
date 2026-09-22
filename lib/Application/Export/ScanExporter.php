<?php

declare(strict_types=1);

namespace Gorshkov\CatalogSentinel\Application\Export;

use Gorshkov\CatalogSentinel\Application\DTO\ScanRecord;

final class ScanExporter
{
    public function json(ScanRecord $scan): string
    {
        return json_encode($this->data($scan), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    public function csv(ScanRecord $scan): string
    {
        $stream = fopen('php://temp', 'w+b');
        if ($stream === false) {
            throw new \RuntimeException('Unable to create CSV stream.');
        }
        fputcsv($stream, ['field', 'value'], ';', '"', '');
        foreach ($this->data($scan) as $field => $value) {
            $serialized = is_scalar($value) || $value === null
                ? (string) $value
                : json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            fputcsv($stream, [$this->safeCell($field), $this->safeCell($serialized)], ';', '"', '');
        }
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return "\xEF\xBB\xBF" . ($csv === false ? '' : $csv);
    }

    /** @return array<string, mixed> */
    private function data(ScanRecord $scan): array
    {
        return [
            'id' => $scan->id,
            'created_at' => $scan->createdAt,
            'status' => $scan->status,
            'decision' => $scan->decision,
            'source_key' => $scan->sourceKey,
            'source_label' => $scan->sourceLabel,
            'document_kind' => $scan->documentKind,
            'file_name' => $scan->file->fileName,
            'file_size' => $scan->file->size,
            'identity_key' => $scan->file->identityKey,
            'metrics' => $scan->metrics,
            'samples' => $scan->samples,
            'warnings' => $scan->warnings,
        ];
    }

    private function safeCell(string $value): string
    {
        return preg_match('/^[=+\-@\t\r]/', $value) === 1 ? "'" . $value : $value;
    }
}
