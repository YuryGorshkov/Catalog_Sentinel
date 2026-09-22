<?php

declare(strict_types=1);

namespace Gorshkov\CatalogSentinel\Bitrix\Admin;

final class AdminGuard
{
    public const MODULE_ID = 'gorshkov.catalogsentinel';

    public static function right(): string
    {
        global $APPLICATION;

        return (string) $APPLICATION->GetGroupRight(self::MODULE_ID);
    }

    public static function requireRead(): void
    {
        if (self::right() < 'R') {
            $GLOBALS['APPLICATION']->AuthForm(GetMessage('ACCESS_DENIED'));
        }
    }

    public static function requireWrite(): void
    {
        if (self::right() < 'W') {
            throw new \RuntimeException((string) GetMessage('ACCESS_DENIED'));
        }
    }

    public static function requirePost(): void
    {
        self::requireWrite();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !check_bitrix_sessid()) {
            throw new \RuntimeException('Invalid request or CSRF token.');
        }
    }
}
