<?php

declare(strict_types=1);

namespace Gorshkov\CatalogSentinel\Infrastructure\Persistence\ORM;

use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\DatetimeField;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\StringField;
use Bitrix\Main\ORM\Fields\TextField;

final class ScanTable extends DataManager
{
    public const TABLE_NAME = 'b_gcs_scan';

    public static function getTableName(): string
    {
        return self::TABLE_NAME;
    }

    public static function getMap(): array
    {
        return [
            (new IntegerField('ID'))->configurePrimary()->configureAutocomplete(),
            (new DatetimeField('CREATED_AT'))->configureRequired(),
            (new DatetimeField('UPDATED_AT'))->configureRequired(),
            new DatetimeField('FINISHED_AT'),
            new DatetimeField('IMPORTED_AT'),
            (new StringField('MODE'))->configureRequired()->configureSize(16),
            (new StringField('STATUS'))->configureRequired()->configureSize(32),
            (new StringField('DECISION'))->configureSize(16),
            (new StringField('SOURCE_KEY'))->configureRequired()->configureSize(64),
            (new StringField('SOURCE_LABEL'))->configureRequired()->configureSize(255),
            (new StringField('DOCUMENT_KIND'))->configureRequired()->configureSize(32),
            (new StringField('FILE_NAME'))->configureRequired()->configureSize(255),
            (new StringField('PATH_HASH'))->configureRequired()->configureSize(64),
            (new StringField('CONTENT_HASH'))->configureSize(64),
            (new IntegerField('FILE_SIZE'))->configureRequired(),
            new IntegerField('FILE_MTIME'),
            (new StringField('IDENTITY_KEY'))->configureRequired()->configureSize(64),
            (new IntegerField('POLICY_VERSION'))->configureRequired(),
            (new IntegerField('ANALYZER_SCHEMA_VERSION'))->configureRequired(),
            (new TextField('METRICS_JSON'))->configureRequired(),
            (new TextField('SAMPLES_JSON'))->configureRequired(),
            (new TextField('WARNINGS_JSON'))->configureRequired(),
            (new TextField('RULE_RESULTS_JSON'))->configureRequired(),
            (new StringField('ERROR_CODE'))->configureSize(100),
            (new StringField('ERROR_MESSAGE'))->configureSize(2000),
            new IntegerField('DURATION_MS'),
            new IntegerField('MEMORY_DELTA_BYTES'),
            (new StringField('IS_DRY_RUN'))->configureRequired()->configureSize(1),
            new DatetimeField('CACHE_UNTIL'),
        ];
    }
}
