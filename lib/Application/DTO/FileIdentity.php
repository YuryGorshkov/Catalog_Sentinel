<?php

declare(strict_types=1);

namespace Gorshkov\CatalogSentinel\Application\DTO;

final class FileIdentity
{
    public function __construct(
        public readonly string $fileName,
        public readonly int $size,
        public readonly ?int $modifiedAt,
        public readonly string $pathHash,
        public readonly ?string $contentHash,
        public readonly string $identityKey,
    ) {
    }
}
