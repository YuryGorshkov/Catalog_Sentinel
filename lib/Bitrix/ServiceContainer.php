<?php

declare(strict_types=1);

namespace Gorshkov\CatalogSentinel\Bitrix;

use Gorshkov\CatalogSentinel\Application\Cleanup\CleanupHistory;
use Gorshkov\CatalogSentinel\Application\Scan\MarkImportSuccessful;
use Gorshkov\CatalogSentinel\Application\Scan\ScanIncomingFile;
use Gorshkov\CatalogSentinel\Domain\Rule\DefaultRuleSet;
use Gorshkov\CatalogSentinel\Domain\Rule\RuleEngine;
use Gorshkov\CatalogSentinel\Infrastructure\Config\BitrixOptionSettingsProvider;
use Gorshkov\CatalogSentinel\Infrastructure\Notification\BitrixNotifier;
use Gorshkov\CatalogSentinel\Infrastructure\Persistence\BitrixBaselineRepository;
use Gorshkov\CatalogSentinel\Infrastructure\Persistence\BitrixScanRepository;
use Gorshkov\CatalogSentinel\Infrastructure\Support\BitrixEventLogger;
use Gorshkov\CatalogSentinel\Infrastructure\Support\SafeFileIdentityFactory;
use Gorshkov\CatalogSentinel\Infrastructure\Support\SafeSourceKeyResolver;
use Gorshkov\CatalogSentinel\Infrastructure\Support\SystemClock;
use Gorshkov\CatalogSentinel\Infrastructure\Xml\XmlReaderCommerceMlAnalyzer;

final class ServiceContainer
{
    private static ?self $instance = null;
    private ?ScanIncomingFile $scanner = null;
    private ?MarkImportSuccessful $success = null;
    private ?CleanupHistory $cleanup = null;

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    public static function reset(): void
    {
        self::$instance = null;
    }

    public function scanner(): ScanIncomingFile
    {
        if ($this->scanner === null) {
            $clock = new SystemClock();
            $this->scanner = new ScanIncomingFile(
                new BitrixOptionSettingsProvider(),
                new XmlReaderCommerceMlAnalyzer(),
                new RuleEngine(DefaultRuleSet::create()),
                new BitrixScanRepository(),
                new BitrixBaselineRepository(),
                new SafeFileIdentityFactory(),
                new SafeSourceKeyResolver(),
                new BitrixNotifier($clock),
                new BitrixEventLogger(),
                $clock,
            );
        }

        return $this->scanner;
    }

    public function success(): MarkImportSuccessful
    {
        if ($this->success === null) {
            $this->success = new MarkImportSuccessful(
                new BitrixScanRepository(),
                new BitrixBaselineRepository(),
                new SafeFileIdentityFactory(),
                new SystemClock(),
                new BitrixEventLogger(),
            );
        }

        return $this->success;
    }

    public function cleanup(): CleanupHistory
    {
        if ($this->cleanup === null) {
            $this->cleanup = new CleanupHistory(
                new BitrixScanRepository(),
                new BitrixBaselineRepository(),
                new BitrixOptionSettingsProvider(),
                new SystemClock(),
            );
        }

        return $this->cleanup;
    }
}
