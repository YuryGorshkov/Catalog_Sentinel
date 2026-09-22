<?php

declare(strict_types=1);

namespace Gorshkov\CatalogSentinel\Infrastructure\Persistence\ORM;

use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\DatetimeField;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\StringField;
use Bitrix\Main\ORM\Fields\TextField;

final class ViolationTable extends DataManager
{
    public const TABLE_NAME = 'b_gcs_violation';

    public static function getTableName(): string
    {
        return self::TABLE_NAME;
    }

    public static function getMap(): array
    {
        return [
            (new IntegerField('ID'))->configurePrimary()->configureAutocomplete(),
            (new IntegerField('SCAN_ID'))->configureRequired(),
            (new DatetimeField('CREATED_AT'))->configureRequired(),
            (new StringField('RULE_CODE'))->configureRequired()->configureSize(100),
            (new StringField('OUTCOME'))->configureRequired()->configureSize(16),
            (new StringField('REASON_CODE'))->configureRequired()->configureSize(100),
            (new TextField('ACTUAL_JSON'))->configureRequired(),
            (new TextField('THRESHOLDS_JSON'))->configureRequired(),
            new TextField('BASELINE_JSON'),
            new TextField('SAMPLE_JSON'),
        ];
    }
}
