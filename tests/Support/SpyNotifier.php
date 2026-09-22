<?php

declare(strict_types=1);

namespace Gorshkov\CatalogSentinel\Tests\Support;

use Gorshkov\CatalogSentinel\Application\Contract\NotifierInterface;
use Gorshkov\CatalogSentinel\Application\DTO\ScanRecord;

final class SpyNotifier implements NotifierInterface
{
    /** @var list<ScanRecord> */
    public array $notifications = [];
    public bool $throw = false;

    public function notifyBlocked(ScanRecord $scan): void
    {
        if ($this->throw) {
            throw new \RuntimeException('Notification failed.');
        }
        $this->notifications[] = $scan;
    }
}
