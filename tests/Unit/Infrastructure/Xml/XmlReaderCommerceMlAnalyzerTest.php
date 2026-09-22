<?php

declare(strict_types=1);

namespace Gorshkov\CatalogSentinel\Tests\Unit\Infrastructure\Xml;

use Gorshkov\CatalogSentinel\Domain\Analysis\DocumentKind;
use Gorshkov\CatalogSentinel\Domain\Analysis\MetricNames;
use Gorshkov\CatalogSentinel\Domain\Policy\Mode;
use Gorshkov\CatalogSentinel\Domain\Policy\PolicySnapshot;
use Gorshkov\CatalogSentinel\Domain\Policy\ScanErrorPolicy;
use Gorshkov\CatalogSentinel\Infrastructure\Xml\XmlReaderCommerceMlAnalyzer;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class XmlReaderCommerceMlAnalyzerTest extends TestCase
{
    private const FIXTURES = __DIR__ . '/../../../Fixture/commerce_ml';

    /** @return iterable<string, array{string, string}> */
    public static function classifications(): iterable
    {
        yield 'catalog' => ['minimal_import.xml', DocumentKind::CATALOG];
        yield 'offers' => ['normal_offers.xml', DocumentKind::OFFERS];
        yield 'namespace' => ['namespace_offers.xml', DocumentKind::OFFERS];
        yield 'classifier' => ['classifier.xml', DocumentKind::CLASSIFIER];
        yield 'unknown' => ['unknown.xml', DocumentKind::UNKNOWN_COMMERCE_ML];
        yield 'other XML' => ['other.xml', DocumentKind::NOT_COMMERCE_ML];
        yield 'malformed' => ['malformed.xml', DocumentKind::MALFORMED];
        yield 'empty' => ['empty.xml', DocumentKind::MALFORMED];
        yield 'doctype' => ['doctype.xml', DocumentKind::MALFORMED];
    }

    #[DataProvider('classifications')]
    public function testClassifiesByStructure(string $fixture, string $expected): void
    {
        $result = $this->analyzer()->analyze(self::FIXTURES . '/' . $fixture, PolicySnapshot::defaults());

        self::assertSame($expected, $result->documentKind);
    }

    public function testAggregatesStockAndPricesWithoutDoubleCounting(): void
    {
        $result = $this->analyzer()->analyze(self::FIXTURES . '/normal_offers.xml', PolicySnapshot::defaults());
        $metrics = $result->metrics;

        self::assertSame(3, $metrics->get(MetricNames::OBJECTS_TOTAL));
        self::assertSame(3, $metrics->get(MetricNames::OBJECTS_WITH_ID));
        self::assertSame(3, $metrics->get(MetricNames::STOCK_EXPLICIT));
        self::assertSame(2, $metrics->get(MetricNames::STOCK_ZERO_OR_NEGATIVE));
        self::assertEqualsWithDelta(2 / 3, $metrics->get(MetricNames::STOCK_ZERO_RATIO), 0.000001);
        self::assertSame(1, $metrics->get(MetricNames::STOCK_NEGATIVE_VALUES));
        self::assertSame(2, $metrics->get(MetricNames::PRICE_EXPLICIT));
        self::assertSame(1, $metrics->get(MetricNames::PRICE_ALL_ZERO));
        self::assertSame(3, $metrics->get(MetricNames::PRICE_VALUES_TOTAL));
    }

    public function testMissingTagsAreNotExplicitZerosAndInvalidNumbersAreNotZeros(): void
    {
        $result = $this->analyzer()->analyze(self::FIXTURES . '/invalid_numbers.xml', PolicySnapshot::defaults());

        self::assertSame(3, $result->metrics->get(MetricNames::OBJECTS_TOTAL));
        self::assertSame(1, $result->metrics->get(MetricNames::STOCK_EXPLICIT));
        self::assertSame(1, $result->metrics->get(MetricNames::STOCK_ZERO_OR_NEGATIVE));
        self::assertSame(1, $result->metrics->get(MetricNames::PRICE_EXPLICIT));
        self::assertSame(1, $result->metrics->get(MetricNames::PRICE_ALL_ZERO));
        self::assertSame(2, $result->metrics->get(MetricNames::VALUE_ERRORS));
    }

    public function testExplicitEmptyPropertyDiffersFromAbsentProperty(): void
    {
        $policy = $this->policyWithProperties(['COLOR']);
        $result = $this->analyzer()->analyze(self::FIXTURES . '/properties.xml', $policy);

        self::assertSame(4, $result->metrics->get(MetricNames::OBJECTS_TOTAL));
        self::assertSame(1, $result->metrics->get(MetricNames::OBJECTS_MISSING_ID));
        self::assertSame(2, $result->metrics->get(MetricNames::property('COLOR', 'objects_with_explicit_value')));
        self::assertSame(1, $result->metrics->get(MetricNames::property('COLOR', 'objects_explicit_empty')));
        self::assertSame(0.5, $result->metrics->get(MetricNames::property('COLOR', 'empty_ratio')));
    }

    public function testNamespaceAndCommaNumbersAreSupported(): void
    {
        $result = $this->analyzer()->analyze(self::FIXTURES . '/namespace_offers.xml', PolicySnapshot::defaults());

        self::assertTrue($result->wellFormed);
        self::assertSame(1, $result->metrics->get(MetricNames::STOCK_POSITIVE));
        self::assertSame(1, $result->metrics->get(MetricNames::PRICE_POSITIVE));
    }

    public function testDoctypeIsRejectedWithoutEntitySubstitution(): void
    {
        $result = $this->analyzer()->analyze(self::FIXTURES . '/doctype.xml', PolicySnapshot::defaults());

        self::assertFalse($result->wellFormed);
        self::assertSame('UNSAFE_XML', $result->errorCode);
    }

    public function testSamplesAndIdentifiersAreBounded(): void
    {
        $policy = new PolicySnapshot(
            1,
            1,
            Mode::OBSERVE,
            ScanErrorPolicy::ALLOW_AND_ALERT,
            5,
            3,
            90,
            1,
            30,
            false,
            15,
            [],
            PolicySnapshot::defaultRules(),
        );
        $result = $this->analyzer()->analyze(self::FIXTURES . '/normal_offers.xml', $policy);

        self::assertCount(1, $result->samples['stock_zero_or_negative']);
        self::assertLessThanOrEqual(200, strlen($result->samples['stock_zero_or_negative'][0]));
    }

    public function testUrlPathIsRejectedBeforeXmlReader(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->analyzer()->analyze('https://example.invalid/file.xml', PolicySnapshot::defaults());
    }

    private function analyzer(): XmlReaderCommerceMlAnalyzer
    {
        return new XmlReaderCommerceMlAnalyzer();
    }

    /** @param list<string> $properties */
    private function policyWithProperties(array $properties): PolicySnapshot
    {
        return new PolicySnapshot(
            1,
            1,
            Mode::OBSERVE,
            ScanErrorPolicy::ALLOW_AND_ALERT,
            5,
            3,
            90,
            50,
            30,
            false,
            15,
            $properties,
            PolicySnapshot::defaultRules(),
        );
    }
}
