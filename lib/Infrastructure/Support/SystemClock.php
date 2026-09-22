<?php

declare(strict_types=1);

namespace Gorshkov\CatalogSentinel\Infrastructure\Support;

use DateTimeImmutable;
use Gorshkov\CatalogSentinel\Application\Contract\ClockInterface;

final class SystemClock implements ClockInterface
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable();
    }
}
