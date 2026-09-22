<?php

declare(strict_types=1);

namespace Gorshkov\CatalogSentinel\Tests\Support;

use DateTimeImmutable;
use Gorshkov\CatalogSentinel\Application\Contract\ClockInterface;

final class FrozenClock implements ClockInterface
{
    public function __construct(private DateTimeImmutable $time)
    {
    }

    public function now(): DateTimeImmutable
    {
        return $this->time;
    }

    public function advance(string $modifier): void
    {
        $this->time = $this->time->modify($modifier);
    }
}
