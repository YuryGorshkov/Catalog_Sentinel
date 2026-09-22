<?php

declare(strict_types=1);

namespace Gorshkov\CatalogSentinel\Tests\Unit\Domain\Rule;

use Gorshkov\CatalogSentinel\Domain\Analysis\AnalysisMetrics;
use Gorshkov\CatalogSentinel\Domain\Analysis\AnalysisResult;
use Gorshkov\CatalogSentinel\Domain\Analysis\DocumentKind;
use Gorshkov\CatalogSentinel\Domain\Analysis\MetricNames;
use Gorshkov\CatalogSentinel\Domain\Baseline\BaselineSnapshot;
use Gorshkov\CatalogSentinel\Domain\Policy\Mode;
use Gorshkov\CatalogSentinel\Domain\Policy\PolicySnapshot;
use Gorshkov\CatalogSentinel\Domain\Policy\ScanErrorPolicy;
use Gorshkov\CatalogSentinel\Domain\Rule\Decision;
use Gorshkov\CatalogSentinel\Domain\Rule\DefaultRuleSet;
use Gorshkov\CatalogSentinel\Domain\Rule\RuleContext;
use Gorshkov\CatalogSentinel\Domain\Rule\RuleEngine;
use Gorshkov\CatalogSentinel\Domain\Rule\RuleOutcome;
use Gorshkov\CatalogSentinel\Domain\Rule\Rules\StockZeroSpikeRule;
use PHPUnit\Framework\TestCase;

final class RuleEngineTest extends TestCase
{
    public function testAnomalyContainsMeasuredValuesAndBlocks(): void
    {
        $analysis = $this->offersAnalysis([
            MetricNames::OBJECTS_TOTAL => 1000,
            MetricNames::OBJECTS_MISSING_ID => 0,
            MetricNames::STOCK_EXPLICIT => 1000,
            MetricNames::STOCK_ZERO_OR_NEGATIVE => 920,
            MetricNames::STOCK_ZERO_RATIO => 0.92,
            MetricNames::PRICE_EXPLICIT => 1000,
            MetricNames::PRICE_ALL_ZERO => 20,
            MetricNames::PRICE_ZERO_RATIO => 0.02,
            MetricNames::BYTES => 2_000_000,
        ]);
        $baseline = $this->readyBaseline([
            MetricNames::OBJECTS_TOTAL => 1000,
            MetricNames::STOCK_ZERO_RATIO => 0.08,
            MetricNames::PRICE_ZERO_RATIO => 0.02,
            MetricNames::BYTES => 2_000_000,
        ]);

        $evaluation = $this->engine()->evaluate(new RuleContext(
            $analysis,
            PolicySnapshot::defaults(Mode::PROTECT),
            $baseline,
            '2026-09-22T12:00:00+03:00',
        ));

        self::assertSame(Decision::BLOCK, $evaluation->decision);
        $stock = $this->findResult($evaluation->results, StockZeroSpikeRule::CODE);
        self::assertSame(RuleOutcome::BLOCK, $stock->outcome);
        self::assertSame(0.92, $stock->actual['ratio']);
        self::assertEqualsWithDelta(0.84, $stock->actual['ratio_delta'], 0.000001);
        self::assertSame(0.80, $stock->thresholds['min_current_ratio']);
        self::assertSame(0.08, $stock->baseline['median_ratio']);
    }

    public function testObserveDoesNotChangeCalculatedDecision(): void
    {
        $analysis = $this->offersAnalysis([
            MetricNames::OBJECTS_TOTAL => 100,
            MetricNames::OBJECTS_MISSING_ID => 0,
            MetricNames::STOCK_EXPLICIT => 100,
            MetricNames::STOCK_ZERO_OR_NEGATIVE => 100,
            MetricNames::STOCK_ZERO_RATIO => 1.0,
            MetricNames::BYTES => 2_000_000,
        ]);
        $baseline = $this->readyBaseline([
            MetricNames::OBJECTS_TOTAL => 100,
            MetricNames::STOCK_ZERO_RATIO => 0.0,
            MetricNames::BYTES => 2_000_000,
        ]);

        $evaluation = $this->engine()->evaluate(new RuleContext(
            $analysis,
            PolicySnapshot::defaults(Mode::OBSERVE),
            $baseline,
            'now',
        ));

        self::assertSame(Decision::BLOCK, $evaluation->decision);
    }

    public function testBaselineRulesCannotBlockBeforeBaselineIsReady(): void
    {
        $analysis = $this->offersAnalysis([
            MetricNames::OBJECTS_TOTAL => 1000,
            MetricNames::OBJECTS_MISSING_ID => 0,
            MetricNames::STOCK_EXPLICIT => 1000,
            MetricNames::STOCK_ZERO_OR_NEGATIVE => 1000,
            MetricNames::STOCK_ZERO_RATIO => 1.0,
            MetricNames::PRICE_EXPLICIT => 1000,
            MetricNames::PRICE_ALL_ZERO => 1000,
            MetricNames::PRICE_ZERO_RATIO => 1.0,
            MetricNames::BYTES => 100,
        ]);
        $baseline = BaselineSnapshot::empty('source', DocumentKind::OFFERS, 1, 3);

        $evaluation = $this->engine()->evaluate(new RuleContext(
            $analysis,
            PolicySnapshot::defaults(),
            $baseline,
            'now',
        ));

        self::assertSame(Decision::PASS, $evaluation->decision);
        self::assertSame(
            RuleOutcome::NOT_APPLICABLE,
            $this->findResult($evaluation->results, StockZeroSpikeRule::CODE)->outcome,
        );
    }

    public function testEqualityAtStockThresholdTriggers(): void
    {
        $analysis = $this->offersAnalysis([
            MetricNames::OBJECTS_TOTAL => 100,
            MetricNames::OBJECTS_MISSING_ID => 0,
            MetricNames::STOCK_EXPLICIT => 100,
            MetricNames::STOCK_ZERO_OR_NEGATIVE => 100,
            MetricNames::STOCK_ZERO_RATIO => 0.80,
            MetricNames::BYTES => 2_000_000,
        ]);
        $baseline = $this->readyBaseline([
            MetricNames::OBJECTS_TOTAL => 100,
            MetricNames::STOCK_ZERO_RATIO => 0.30,
            MetricNames::BYTES => 2_000_000,
        ]);

        $evaluation = $this->engine()->evaluate(new RuleContext(
            $analysis,
            PolicySnapshot::defaults(),
            $baseline,
            'now',
        ));

        self::assertSame(RuleOutcome::BLOCK, $this->findResult($evaluation->results, StockZeroSpikeRule::CODE)->outcome);
    }

    public function testMalformedXmlIsAlwaysABlockingBusinessDecision(): void
    {
        $analysis = new AnalysisResult(
            DocumentKind::MALFORMED,
            false,
            false,
            new AnalysisMetrics([]),
            errorCode: 'MALFORMED_XML',
        );
        $baseline = BaselineSnapshot::empty('source', DocumentKind::MALFORMED, 1, 3);

        $evaluation = $this->engine()->evaluate(new RuleContext(
            $analysis,
            PolicySnapshot::defaults(),
            $baseline,
            'now',
        ));

        self::assertSame(Decision::BLOCK, $evaluation->decision);
    }

    public function testIdentifierRuleWarnsAtExactThreshold(): void
    {
        $analysis = $this->offersAnalysis([
            MetricNames::OBJECTS_TOTAL => 500,
            MetricNames::OBJECTS_MISSING_ID => 10,
            MetricNames::BYTES => 100,
        ]);

        $evaluation = $this->engine()->evaluate(new RuleContext(
            $analysis,
            PolicySnapshot::defaults(),
            BaselineSnapshot::empty('source', DocumentKind::OFFERS, 1, 3),
            'now',
        ));

        self::assertSame(Decision::WARN, $evaluation->decision);
    }

    /** @param array<string, int|float> $metrics */
    private function offersAnalysis(array $metrics): AnalysisResult
    {
        return new AnalysisResult(
            DocumentKind::OFFERS,
            true,
            true,
            new AnalysisMetrics($metrics),
            ['stock_zero_or_negative' => ['offer-1']],
        );
    }

    /** @param array<string, int|float> $medians */
    private function readyBaseline(array $medians): BaselineSnapshot
    {
        return new BaselineSnapshot('source', DocumentKind::OFFERS, 1, 3, 3, array_map('floatval', $medians));
    }

    private function engine(): RuleEngine
    {
        return new RuleEngine(DefaultRuleSet::create());
    }

    /**
     * @param list<\Gorshkov\CatalogSentinel\Domain\Rule\RuleResult> $results
     */
    private function findResult(array $results, string $code): \Gorshkov\CatalogSentinel\Domain\Rule\RuleResult
    {
        foreach ($results as $result) {
            if ($result->ruleCode === $code) {
                return $result;
            }
        }

        self::fail('Rule result not found: ' . $code);
    }

    public function testCustomPropertyRuleCanBlockExplicitClears(): void
    {
        $propertyId = 'COLOR';
        $rules = PolicySnapshot::defaultRules();
        $rules['property.empty_spike']['enabled'] = true;
        $policy = new PolicySnapshot(
            2,
            1,
            Mode::PROTECT,
            ScanErrorPolicy::ALLOW_AND_ALERT,
            5,
            3,
            90,
            50,
            30,
            false,
            15,
            [$propertyId],
            $rules,
        );
        $ratioKey = MetricNames::property($propertyId, 'empty_ratio');
        $analysis = new AnalysisResult(
            DocumentKind::CATALOG,
            true,
            true,
            new AnalysisMetrics([
                MetricNames::OBJECTS_TOTAL => 100,
                MetricNames::OBJECTS_MISSING_ID => 0,
                MetricNames::BYTES => 100,
                MetricNames::property($propertyId, 'objects_with_explicit_value') => 100,
                MetricNames::property($propertyId, 'objects_explicit_empty') => 90,
                $ratioKey => 0.90,
            ]),
        );
        $baseline = new BaselineSnapshot(
            'source',
            DocumentKind::CATALOG,
            1,
            3,
            3,
            [MetricNames::OBJECTS_TOTAL => 100.0, $ratioKey => 0.10],
        );

        $evaluation = $this->engine()->evaluate(new RuleContext($analysis, $policy, $baseline, 'now'));

        self::assertSame(Decision::BLOCK, $evaluation->decision);
    }
}
