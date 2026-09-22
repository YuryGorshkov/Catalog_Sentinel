<?php

declare(strict_types=1);

namespace Gorshkov\CatalogSentinel\Application\Scan;

use DateInterval;
use Gorshkov\CatalogSentinel\Application\Contract\BaselineRepositoryInterface;
use Gorshkov\CatalogSentinel\Application\Contract\ClockInterface;
use Gorshkov\CatalogSentinel\Application\Contract\DocumentAnalyzerInterface;
use Gorshkov\CatalogSentinel\Application\Contract\FileIdentityFactoryInterface;
use Gorshkov\CatalogSentinel\Application\Contract\LoggerInterface;
use Gorshkov\CatalogSentinel\Application\Contract\NotifierInterface;
use Gorshkov\CatalogSentinel\Application\Contract\ScanRepositoryInterface;
use Gorshkov\CatalogSentinel\Application\Contract\SettingsProviderInterface;
use Gorshkov\CatalogSentinel\Application\Contract\SourceKeyResolverInterface;
use Gorshkov\CatalogSentinel\Application\DTO\FileIdentity;
use Gorshkov\CatalogSentinel\Application\DTO\ScanOutcome;
use Gorshkov\CatalogSentinel\Application\DTO\ScanRecord;
use Gorshkov\CatalogSentinel\Application\DTO\SourceIdentity;
use Gorshkov\CatalogSentinel\Domain\Analysis\DocumentKind;
use Gorshkov\CatalogSentinel\Domain\Policy\Mode;
use Gorshkov\CatalogSentinel\Domain\Policy\ScanErrorPolicy;
use Gorshkov\CatalogSentinel\Domain\Rule\Decision;
use Gorshkov\CatalogSentinel\Domain\Rule\RuleContext;
use Gorshkov\CatalogSentinel\Domain\Rule\RuleEngine;
use Gorshkov\CatalogSentinel\Domain\Scan\ScanStatus;
use Throwable;

final class ScanIncomingFile
{
    public function __construct(
        private readonly SettingsProviderInterface $settings,
        private readonly DocumentAnalyzerInterface $analyzer,
        private readonly RuleEngine $rules,
        private readonly ScanRepositoryInterface $scans,
        private readonly BaselineRepositoryInterface $baselines,
        private readonly FileIdentityFactoryInterface $identities,
        private readonly SourceKeyResolverInterface $sources,
        private readonly NotifierInterface $notifier,
        private readonly LoggerInterface $logger,
        private readonly ClockInterface $clock,
    ) {
    }

    /** @param array<string, mixed> $parameters */
    public function execute(
        string $path,
        array $parameters = [],
        bool $dryRun = false,
        ?string $sourceLabel = null,
        ?string $sourceKeyOverride = null,
    ): ScanOutcome {
        $policy = $this->settings->current();
        if (!$dryRun && $policy->mode === Mode::DISABLED) {
            return new ScanOutcome(false, '', null);
        }

        $now = $this->clock->now();
        $identity = null;
        try {
            $identity = $this->identities->create($path);
            if (!$dryRun) {
                $cached = $this->scans->findCached($identity->identityKey, $policy->version, $now);
                if ($cached !== null) {
                    return $this->outcome($cached, $policy->mode, true);
                }
            }

            $analysis = $this->analyzer->analyze($path, $policy);
            if ($analysis->errorCode === 'SCAN_LIMIT_EXCEEDED') {
                throw new \RuntimeException('The analyzer soft time limit was exceeded.');
            }
            $source = $sourceKeyOverride !== null && preg_match('/^[a-f0-9]{64}$/D', $sourceKeyOverride) === 1
                ? new SourceIdentity($sourceKeyOverride, trim((string) $sourceLabel) ?: 'selected baseline')
                : $this->sources->resolve($parameters, $analysis, $sourceLabel);
            $baseline = $this->baselines->get($source->key, $analysis->documentKind, $policy);
            $evaluation = $this->rules->evaluate(new RuleContext($analysis, $policy, $baseline, $now->format(DATE_ATOM)));
            $status = $this->status($analysis->documentKind, $evaluation->decision, $policy->mode, $dryRun);
            $record = new ScanRecord(
                null,
                $now->format(DATE_ATOM),
                $now->format(DATE_ATOM),
                $now->format(DATE_ATOM),
                null,
                $policy->mode,
                $status,
                $evaluation->decision,
                $source->key,
                $source->label,
                $analysis->documentKind,
                $identity,
                $policy->version,
                $policy->analyzerSchemaVersion,
                $analysis->metrics->all(),
                $analysis->samples,
                $analysis->parserWarnings,
                $analysis->errorCode,
                null,
                $analysis->durationMs,
                $analysis->peakMemoryDeltaBytes,
                $dryRun,
                $now->add(new DateInterval('PT' . $policy->decisionCacheMinutes . 'M'))->format(DATE_ATOM),
                $evaluation->results,
            );
            try {
                $record = $this->scans->save($record);
            } catch (Throwable $exception) {
                $this->logger->error('scan.persistence_failed', ['exception' => $exception::class]);
            }
            if (
                !$dryRun
                && $policy->notificationEnabled
                && $policy->mode === Mode::PROTECT
                && $evaluation->decision === Decision::BLOCK
            ) {
                try {
                    $this->notifier->notifyBlocked($record);
                } catch (Throwable $exception) {
                    $this->logger->warning('notification.failed', ['exception' => $exception::class]);
                }
            }

            return $this->outcome($record, $policy->mode);
        } catch (Throwable $exception) {
            return $this->errorOutcome($identity, $policy, $now->format(DATE_ATOM), $exception, $dryRun);
        }
    }

    private function status(string $kind, string $decision, string $mode, bool $dryRun): string
    {
        if ($dryRun) {
            return ScanStatus::DRY_RUN;
        }
        if (!DocumentKind::isSupported($kind) && $kind !== DocumentKind::MALFORMED) {
            return ScanStatus::UNSUPPORTED;
        }
        if ($mode === Mode::OBSERVE) {
            return ScanStatus::OBSERVED;
        }

        return $decision === Decision::BLOCK ? ScanStatus::BLOCKED : ScanStatus::ALLOWED;
    }

    private function outcome(ScanRecord $record, string $mode, bool $cached = false): ScanOutcome
    {
        $blocked = $mode === Mode::PROTECT && $record->decision === Decision::BLOCK;
        $message = $blocked ? 'CATALOG_SENTINEL_BLOCK' : '';

        return new ScanOutcome($blocked, $message, $record, $cached);
    }

    private function errorOutcome(
        ?FileIdentity $identity,
        \Gorshkov\CatalogSentinel\Domain\Policy\PolicySnapshot $policy,
        string $at,
        Throwable $exception,
        bool $dryRun,
    ): ScanOutcome {
        $blocked = $policy->scanErrorPolicy === ScanErrorPolicy::BLOCK_AND_ALERT && !$dryRun;
        $this->logger->error('scan.internal_error', ['exception' => $exception::class]);
        $record = null;
        if ($identity !== null) {
            $record = new ScanRecord(
                null,
                $at,
                $at,
                $at,
                null,
                $policy->mode,
                $blocked ? ScanStatus::SCAN_ERROR_BLOCKED : ScanStatus::SCAN_ERROR_ALLOWED,
                Decision::ERROR,
                hash('sha256', 'default'),
                'default',
                DocumentKind::UNKNOWN_COMMERCE_ML,
                $identity,
                $policy->version,
                $policy->analyzerSchemaVersion,
                [],
                [],
                [],
                'INTERNAL_ERROR',
                'Internal scan failure.',
                null,
                null,
                $dryRun,
                $at,
            );
            try {
                $record = $this->scans->save($record);
            } catch (Throwable) {
                // The system logger above is the final fallback.
            }
        }

        return new ScanOutcome($blocked, $blocked ? 'CATALOG_SENTINEL_SCAN_ERROR' : '', $record);
    }
}
