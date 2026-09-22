<?php

declare(strict_types=1);

namespace Gorshkov\CatalogSentinel\Bitrix\Event;

use Bitrix\Main\Localization\Loc;
use Gorshkov\CatalogSentinel\Bitrix\ServiceContainer;

final class CatalogImportEventHandler
{
    /** @param array<string, mixed> $parameters */
    public static function onBefore(array $parameters, string $absolutePath): string
    {
        try {
            $outcome = ServiceContainer::instance()->scanner()->execute($absolutePath, $parameters);
            if (!$outcome->blocked) {
                return '';
            }
            Loc::loadMessages(__FILE__);
            if ($outcome->scan?->id !== null) {
                return (string) (Loc::getMessage('GCS_IMPORT_BLOCKED', ['#ID#' => '#' . $outcome->scan->id])
                    ?: 'Catalog Sentinel blocked the current XML.');
            }

            return (string) (Loc::getMessage('GCS_IMPORT_BLOCKED_NO_ID') ?: 'Catalog Sentinel blocked the current XML.');
        } catch (\Throwable) {
            return '';
        }
    }
}
