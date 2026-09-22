<?php

declare(strict_types=1);

namespace Gorshkov\CatalogSentinel\Application\Contract;

use Gorshkov\CatalogSentinel\Application\DTO\ScanRecord;

interface NotifierInterface
{
    public function notifyBlocked(ScanRecord $scan): void;
}
