<?php

declare(strict_types=1);

namespace Gorshkov\CatalogSentinel\Bitrix\Admin;

use Bitrix\Main\Config\Option;
use Gorshkov\CatalogSentinel\Application\DTO\ScanOutcome;
use Gorshkov\CatalogSentinel\Bitrix\ServiceContainer;
use Gorshkov\CatalogSentinel\Infrastructure\Support\ByteSize;

final class DryRunUpload
{
    /** @param array<string, mixed> $file */
    public function execute(array $file, ?string $sourceLabel = null, ?string $sourceKey = null): ScanOutcome
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        $size = (int) ($file['size'] ?? 0);
        $uploaded = (string) ($file['tmp_name'] ?? '');
        $maximum = ByteSize::uploadLimit((int) Option::get('gorshkov.catalogsentinel', 'dry_run_max_bytes', '52428800'));
        if ($error !== UPLOAD_ERR_OK || $size < 1 || $size > $maximum || !is_uploaded_file($uploaded)) {
            throw new \RuntimeException('Invalid or oversized upload.');
        }
        $originalName = basename((string) ($file['name'] ?? 'upload.xml'));
        $safeName = preg_replace('/[^A-Za-z0-9._-]+/u', '_', $originalName) ?: 'upload.xml';
        $temporary = \CTempFile::GetFileName('gcs_' . bin2hex(random_bytes(8)) . '_' . substr($safeName, -100));
        \CheckDirPath(dirname($temporary) . '/');
        if (!move_uploaded_file($uploaded, $temporary)) {
            throw new \RuntimeException('Unable to move uploaded file.');
        }
        try {
            return ServiceContainer::instance()->scanner()->execute($temporary, [], true, $sourceLabel, $sourceKey);
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
    }
}
