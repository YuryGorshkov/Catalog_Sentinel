<?php

declare(strict_types=1);

namespace Gorshkov\CatalogSentinel\Application\Contract;

use Gorshkov\CatalogSentinel\Domain\Policy\PolicySnapshot;

interface SettingsProviderInterface
{
    public function current(): PolicySnapshot;
}
