<?php

declare(strict_types=1);

namespace Gorshkov\CatalogSentinel\Tests\Integration;

use DateTimeImmutable;
use Gorshkov\CatalogSentinel\Application\Contract\DocumentAnalyzerInterface;
use Gorshkov\CatalogSentinel\Application\DTO\ScanRecord;
use Gorshkov\CatalogSentinel\Application\Scan\ScanIncomingFile;
use Gorshkov\CatalogSentinel\Domain\Analysis\AnalysisResult;
use Gorshkov\CatalogSentinel\Domain\Policy\Mode;
use Gorshkov\CatalogSentinel\Domain\Policy\PolicySnapshot;
use Gorshkov\CatalogSentinel\Domain\Policy\ScanErrorPolicy;
use Gorshkov\CatalogSentinel\Domain\Rule\DefaultRuleSet;
use Gorshkov\CatalogSentinel\Domain\Rule\RuleEngine;
use Gorshkov\CatalogSentinel\Infrastructure\Support\SafeFileIdentityFactory;
use Gorshkov\CatalogSentinel\Infrastructure\Support\SafeSourceKeyResolver;
use Gorshkov\CatalogSentinel\Infrastructure\Xml\XmlReaderCommerceMlAnalyzer;
use Gorshkov\CatalogSentinel\Tests\Support\ArrayLogger;
use Gorshkov\CatalogSentinel\Tests\Support\FrozenClock;
use Gorshkov\CatalogSentinel\Tests\Support\InMemoryBaselineRepository;
use Gorshkov\CatalogSentinel\Tests\Support\InMemoryScanRepository;
use Gorshkov\CatalogSentinel\Tests\Support\MutableSettingsProvider;
use Gorshkov\CatalogSentinel\Tests\Support\SpyNotifier;
use PHPUnit\Framework\TestCase;

final class ErrorPolicyTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        $this->path = dirname(__DIR__) . '/Fixture/commerce_ml/normal_offers.xml';
    }

    public function testInternalErrorAllowsByDefaultAndCanBeConfiguredToBlock(): void
    {
        $analyzer = new class implements DocumentAnalyzerInterface {
            public function analyze(string $path, PolicySnapshot $policy): AnalysisResult
            {
                throw new \RuntimeException('Synthetic failure.');
            }
        };
        $allow = $this->scanner($this->policy(ScanErrorPolicy::ALLOW_AND_ALERT), $analyzer, new InMemoryScanRepository())->execute($this->path);
        $block = $this->scanner($this->policy(ScanErrorPolicy::BLOCK_AND_ALERT), $analyzer, new InMemoryScanRepository())->execute($this->path);

        self::assertFalse($allow->blocked);
        self::assertSame('SCAN_ERROR_ALLOWED', $allow->scan?->status);
        self::assertTrue($block->blocked);
        self::assertSame('SCAN_ERROR_BLOCKED', $block->scan?->status);
    }

    public function testPersistenceFailureDoesNotCancelCalculatedBlock(): void
    {
        $repository = new class extends InMemoryScanRepository {
            public function save(ScanRecord $scan): ScanRecord
            {
                throw new \RuntimeException('Database unavailable.');
            }
        };
        $path = dirname(__DIR__) . '/Fixture/commerce_ml/malformed.xml';

        $outcome = $this->scanner($this->policy(ScanErrorPolicy::ALLOW_AND_ALERT), new XmlReaderCommerceMlAnalyzer(), $repository)->execute($path);

        self::assertTrue($outcome->blocked);
        self::assertSame('BLOCK', $outcome->scan?->decision);
        self::assertNull($outcome->scan?->id);
    }

    private function scanner(
        PolicySnapshot $policy,
        DocumentAnalyzerInterface $analyzer,
        InMemoryScanRepository $repository,
    ): ScanIncomingFile {
        return new ScanIncomingFile(
            new MutableSettingsProvider($policy),
            $analyzer,
            new RuleEngine(DefaultRuleSet::create()),
            $repository,
            new InMemoryBaselineRepository(),
            new SafeFileIdentityFactory(),
            new SafeSourceKeyResolver(),
            new SpyNotifier(),
            new ArrayLogger(),
            new FrozenClock(new DateTimeImmutable('2026-09-22T10:00:00+03:00')),
        );
    }

    private function policy(string $errorPolicy): PolicySnapshot
    {
        return new PolicySnapshot(
            1,
            1,
            Mode::PROTECT,
            $errorPolicy,
            5,
            3,
            90,
            50,
            30,
            false,
            15,
            [],
            PolicySnapshot::defaultRules(),
        );
    }
}
