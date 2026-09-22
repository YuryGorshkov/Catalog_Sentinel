<?php

declare(strict_types=1);

namespace Gorshkov\CatalogSentinel\Tests\Unit\Infrastructure\Support;

use Gorshkov\CatalogSentinel\Infrastructure\Support\ByteSize;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ByteSizeTest extends TestCase
{
    /** @return iterable<array{string, int}> */
    public static function sizes(): iterable
    {
        yield ['1024', 1024];
        yield ['2K', 2048];
        yield ['8M', 8 * 1024 * 1024];
        yield ['1G', 1024 * 1024 * 1024];
        yield ['invalid', 0];
    }

    #[DataProvider('sizes')]
    public function testParsesIniSizes(string $input, int $expected): void
    {
        self::assertSame($expected, ByteSize::fromIni($input));
    }
}
