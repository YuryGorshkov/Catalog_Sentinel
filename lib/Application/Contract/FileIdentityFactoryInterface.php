<?php

declare(strict_types=1);

namespace Gorshkov\CatalogSentinel\Application\Contract;

use Gorshkov\CatalogSentinel\Application\DTO\FileIdentity;

interface FileIdentityFactoryInterface
{
    public function create(string $path): FileIdentity;
}
