<?php

declare(strict_types=1);

namespace Gorshkov\CatalogSentinel\Tests\Unit\Infrastructure\Xml;

use Gorshkov\CatalogSentinel\Infrastructure\Xml\NumberParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class NumberParserTest extends TestCase
{
    /** @return iterable<string, array{string, float|null}> */
    public static function values(): iterable
    {
        yield 'dot' => ['12.5', 12.5];
        yield 'comma' => ['12,5', 12.5];
        yield 'nbsp' => ["\u{00A0}-0,00\u{00A0}", -0.0];
        yield 'ambiguous separators' => ['1,000.50', null];
        yield 'thousands whitespace' => ['1 000', null];
        yield 'empty' => ['', null];
        yield 'not a number' => ['zero', null];
    }

    #[DataProvider('values')]
    public function testDeterministicParsing(string $raw, ?float $expected): void
    {
        self::assertSame($expected, (new NumberParser())->parse($raw));
    }
}
