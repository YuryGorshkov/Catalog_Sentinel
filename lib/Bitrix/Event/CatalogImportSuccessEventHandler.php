<?php

declare(strict_types=1);

namespace Gorshkov\CatalogSentinel\Bitrix\Event;

use Bitrix\Main\Application;
use Gorshkov\CatalogSentinel\Bitrix\ServiceContainer;

final class CatalogImportSuccessEventHandler
{
    /** @param array<string, mixed> $parameters */
    public static function onSuccess(array $parameters, string $absolutePath): void
    {
        try {
            $connection = Application::getConnection();
            $connection->startTransaction();
            if (ServiceContainer::instance()->success()->execute($absolutePath)) {
                $connection->commitTransaction();
            } else {
                $connection->rollbackTransaction();
            }
        } catch (\Throwable) {
            if (isset($connection)) {
                $connection->rollbackTransaction();
            }
            // Import success must never be changed by an observer failure.
        }
    }
}
