<?php

declare(strict_types=1);

namespace Gorshkov\CatalogSentinel\Application\Contract;

use DateTimeImmutable;

interface ClockInterface
{
    public function now(): DateTimeImmutable;
}
