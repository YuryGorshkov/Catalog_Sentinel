<?php

declare(strict_types=1);

namespace Gorshkov\CatalogSentinel\Bitrix\Agent;

use Bitrix\Main\Loader;
use Gorshkov\CatalogSentinel\Bitrix\ServiceContainer;

final class CleanupAgent
{
    public static function run(): string
    {
        if (Loader::includeModule('gorshkov.catalogsentinel')) {
            ServiceContainer::instance()->cleanup()->execute();
        }

        return self::class . '::run();';
    }
}
