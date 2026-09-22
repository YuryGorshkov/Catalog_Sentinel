<?php

declare(strict_types=1);

namespace Gorshkov\CatalogSentinel\Infrastructure\Support;

use Gorshkov\CatalogSentinel\Application\Contract\FileIdentityFactoryInterface;
use Gorshkov\CatalogSentinel\Application\DTO\FileIdentity;
use InvalidArgumentException;

final class SafeFileIdentityFactory implements FileIdentityFactoryInterface
{
    public function __construct(private readonly int $contentHashMaxBytes = 16_777_216)
    {
    }

    public function create(string $path): FileIdentity
    {
        if (str_contains($path, "\0") || preg_match('#^[a-z][a-z0-9+.-]*://#i', $path) === 1) {
            throw new InvalidArgumentException('Only local paths are allowed.');
        }
        $realPath = realpath($path);
        if ($realPath === false || !is_file($realPath) || !is_readable($realPath)) {
            throw new InvalidArgumentException('File is unavailable.');
        }
        $size = filesize($realPath);
        $mtime = filemtime($realPath);
        if ($size === false) {
            throw new InvalidArgumentException('File size is unavailable.');
        }
        $normalizedPath = str_replace('\\', '/', $realPath);
        $pathHash = hash('sha256', DIRECTORY_SEPARATOR === '\\' ? strtolower($normalizedPath) : $normalizedPath);
        $contentHash = $size <= $this->contentHashMaxBytes ? hash_file('sha256', $realPath) : null;
        $identityKey = hash('sha256', implode('|', [basename($realPath), $size, $mtime ?: 0, $pathHash, $contentHash ?? '']));

        return new FileIdentity(basename($realPath), $size, $mtime === false ? null : $mtime, $pathHash, $contentHash, $identityKey);
    }
}
