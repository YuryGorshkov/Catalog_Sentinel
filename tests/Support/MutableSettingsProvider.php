<?php

declare(strict_types=1);

namespace Gorshkov\CatalogSentinel\Tests\Support;

use Gorshkov\CatalogSentinel\Application\Contract\SettingsProviderInterface;
use Gorshkov\CatalogSentinel\Domain\Policy\PolicySnapshot;

final class MutableSettingsProvider implements SettingsProviderInterface
{
    public function __construct(public PolicySnapshot $policy)
    {
    }

    public function current(): PolicySnapshot
    {
        return $this->policy;
    }
}
