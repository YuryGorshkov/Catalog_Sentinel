<?php

declare(strict_types=1);

namespace Gorshkov\CatalogSentinel\Infrastructure\Persistence\ORM;

use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\DatetimeField;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\StringField;

final class NotificationStateTable extends DataManager
{
    public const TABLE_NAME = 'b_gcs_notification_state';

    public static function getTableName(): string
    {
        return self::TABLE_NAME;
    }

    public static function getMap(): array
    {
        return [
            (new IntegerField('ID'))->configurePrimary()->configureAutocomplete(),
            (new StringField('SOURCE_KEY'))->configureRequired()->configureSize(64),
            (new StringField('RULE_CODE'))->configureRequired()->configureSize(100),
            (new StringField('CHANNEL'))->configureRequired()->configureSize(32),
            (new DatetimeField('LAST_SENT_AT'))->configureRequired(),
            (new IntegerField('LAST_SCAN_ID'))->configureRequired(),
        ];
    }
}
