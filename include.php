<?php

declare(strict_types=1);

defined('B_PROLOG_INCLUDED') || die();

use Bitrix\Main\Loader;

const GORSHKOV_CATALOGSENTINEL_MODULE_ID = 'gorshkov.catalogsentinel';

if (method_exists(Loader::class, 'registerNamespace')) {
    Loader::registerNamespace('Gorshkov\\CatalogSentinel', __DIR__ . '/lib');
}
