<?php

declare(strict_types=1);

namespace Gorshkov\CatalogSentinel\Tests\Integration;

use DateTimeImmutable;
use Gorshkov\CatalogSentinel\Application\Scan\MarkImportSuccessful;
use Gorshkov\CatalogSentinel\Application\Scan\ScanIncomingFile;
use Gorshkov\CatalogSentinel\Domain\Policy\Mode;
use Gorshkov\CatalogSentinel\Domain\Policy\PolicySnapshot;
use Gorshkov\CatalogSentinel\Domain\Policy\ScanErrorPolicy;
use Gorshkov\CatalogSentinel\Domain\Rule\Decision;
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

final class ScanWorkflowTest extends TestCase
{
    /** @var list<string> */
    private array $temporaryFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $file) {
            @unlink($file);
        }
    }

    public function testNormalImportsBuildBaselineAndAnomalyIsBlocked(): void
    {
        $clock = new FrozenClock(new DateTimeImmutable('2026-09-22T10:00:00+03:00'));
        $settings = new MutableSettingsProvider(PolicySnapshot::defaults(Mode::OBSERVE));
        $scans = new InMemoryScanRepository();
        $baselines = new InMemoryBaselineRepository();
        $identity = new SafeFileIdentityFactory();
        $logger = new ArrayLogger();
        $notifier = new SpyNotifier();
        $scanner = $this->scanner($settings, $scans, $baselines, $identity, $notifier, $logger, $clock);
        $success = new MarkImportSuccessful($scans, $baselines, $identity, $clock, $logger);

        for ($i = 1; $i <= 3; ++$i) {
            $path = $this->offersFile('normal-' . $i, 1000, 80);
            $outcome = $scanner->execute($path, ['SITE_ID' => 's1']);
            self::assertFalse($outcome->blocked);
            self::assertSame(Decision::PASS, $outcome->scan?->decision);
            self::assertTrue($success->execute($path));
            $clock->advance('+1 minute');
        }
        self::assertSame(3, $baselines->count());

        $settings->policy = $this->policy(Mode::PROTECT, 2);
        $anomaly = $this->offersFile('anomaly', 1000, 920);
        $outcome = $scanner->execute($anomaly, ['SITE_ID' => 's1']);

        self::assertTrue($outcome->blocked);
        self::assertSame(Decision::BLOCK, $outcome->scan?->decision);
        self::assertNotSame('', $outcome->message);
        self::assertCount(1, $notifier->notifications);
        self::assertFalse($success->execute($anomaly));
        self::assertSame(3, $baselines->count());
    }

    public function testRepeatedBeforeUsesCacheAndSuccessIsIdempotent(): void
    {
        $clock = new FrozenClock(new DateTimeImmutable('2026-09-22T10:00:00+03:00'));
        $settings = new MutableSettingsProvider(PolicySnapshot::defaults());
        $scans = new InMemoryScanRepository();
        $baselines = new InMemoryBaselineRepository();
        $identity = new SafeFileIdentityFactory();
        $logger = new ArrayLogger();
        $scanner = $this->scanner($settings, $scans, $baselines, $identity, new SpyNotifier(), $logger, $clock);
        $success = new MarkImportSuccessful($scans, $baselines, $identity, $clock, $logger);
        $path = $this->offersFile('cached', 100, 8);

        $first = $scanner->execute($path);
        $second = $scanner->execute($path);

        self::assertFalse($first->fromCache);
        self::assertTrue($second->fromCache);
        self::assertCount(1, $scans->all());
        self::assertTrue($success->execute($path));
        self::assertFalse($success->execute($path));
        self::assertSame(1, $baselines->count());
    }

    public function testPolicyVersionInvalidatesBeforeCache(): void
    {
        $clock = new FrozenClock(new DateTimeImmutable('2026-09-22T10:00:00+03:00'));
        $settings = new MutableSettingsProvider($this->policy(Mode::OBSERVE, 1));
        $scans = new InMemoryScanRepository();
        $baselines = new InMemoryBaselineRepository();
        $identity = new SafeFileIdentityFactory();
        $scanner = $this->scanner(
            $settings,
            $scans,
            $baselines,
            $identity,
            new SpyNotifier(),
            new ArrayLogger(),
            $clock,
        );
        $path = $this->offersFile('policy-version', 100, 8);

        $first = $scanner->execute($path);
        $settings->policy = $this->policy(Mode::OBSERVE, 2);
        $second = $scanner->execute($path);

        self::assertFalse($first->fromCache);
        self::assertFalse($second->fromCache);
        self::assertCount(2, $scans->all());
    }

    public function testNotificationFailureDoesNotChangeBlock(): void
    {
        $clock = new FrozenClock(new DateTimeImmutable('2026-09-22T10:00:00+03:00'));
        $settings = new MutableSettingsProvider($this->policy(Mode::PROTECT, 1));
        $scans = new InMemoryScanRepository();
        $baselines = new InMemoryBaselineRepository();
        $identity = new SafeFileIdentityFactory();
        $logger = new ArrayLogger();
        $notifier = new SpyNotifier();
        $notifier->throw = true;
        $scanner = $this->scanner($settings, $scans, $baselines, $identity, $notifier, $logger, $clock);
        $path = $this->offersFile('malformed', 100, 8, true);

        $outcome = $scanner->execute($path);

        self::assertTrue($outcome->blocked);
        self::assertContains('notification.failed', $logger->warnings);
    }

    private function scanner(
        MutableSettingsProvider $settings,
        InMemoryScanRepository $scans,
        InMemoryBaselineRepository $baselines,
        SafeFileIdentityFactory $identity,
        SpyNotifier $notifier,
        ArrayLogger $logger,
        FrozenClock $clock,
    ): ScanIncomingFile {
        return new ScanIncomingFile(
            $settings,
            new XmlReaderCommerceMlAnalyzer(),
            new RuleEngine(DefaultRuleSet::create()),
            $scans,
            $baselines,
            $identity,
            new SafeSourceKeyResolver(),
            $notifier,
            $logger,
            $clock,
        );
    }

    private function policy(string $mode, int $version): PolicySnapshot
    {
        return new PolicySnapshot(
            $version,
            1,
            $mode,
            ScanErrorPolicy::ALLOW_AND_ALERT,
            5,
            3,
            90,
            50,
            30,
            true,
            15,
            [],
            PolicySnapshot::defaultRules(),
        );
    }

    private function offersFile(string $prefix, int $count, int $zeroCount, bool $malformed = false): string
    {
        $path = tempnam(sys_get_temp_dir(), 'gcs_');
        self::assertNotFalse($path);
        $xml = '<?xml version="1.0" encoding="UTF-8"?><КоммерческаяИнформация><ПакетПредложений>'
            . '<Ид>shared-source</Ид><Предложения>';
        for ($i = 0; $i < $count; ++$i) {
            $zero = $i < $zeroCount;
            $xml .= '<Предложение><Ид>' . $prefix . '-' . $i . '</Ид><Количество>' . ($zero ? '0' : '10')
                . '</Количество><Цены><Цена><ЦенаЗаЕдиницу>' . ($zero ? '0' : '100')
                . '</ЦенаЗаЕдиницу></Цена></Цены></Предложение>';
        }
        $xml .= '</Предложения></ПакетПредложений>' . ($malformed ? '' : '</КоммерческаяИнформация>');
        file_put_contents($path, $xml);
        $this->temporaryFiles[] = $path;

        return $path;
    }
}
