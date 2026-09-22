<?php

declare(strict_types=1);

namespace Gorshkov\CatalogSentinel\Infrastructure\Persistence\ORM;

use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\DatetimeField;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\StringField;
use Bitrix\Main\ORM\Fields\TextField;

final class BaselineSampleTable extends DataManager
{
    public const TABLE_NAME = 'b_gcs_baseline_sample';

    public static function getTableName(): string
    {
        return self::TABLE_NAME;
    }

    public static function getMap(): array
    {
        return [
            (new IntegerField('ID'))->configurePrimary()->configureAutocomplete(),
            (new DatetimeField('CREATED_AT'))->configureRequired(),
            (new StringField('SOURCE_KEY'))->configureRequired()->configureSize(64),
            (new StringField('DOCUMENT_KIND'))->configureRequired()->configureSize(32),
            (new IntegerField('ANALYZER_SCHEMA_VERSION'))->configureRequired(),
            (new IntegerField('SCAN_ID'))->configureRequired(),
            (new StringField('ORIGIN'))->configureRequired()->configureSize(16),
            new IntegerField('ACCEPTED_BY'),
            (new TextField('METRICS_JSON'))->configureRequired(),
        ];
    }
}
