<?php

declare(strict_types=1);

namespace Gorshkov\CatalogSentinel\Infrastructure\Xml;

use Gorshkov\CatalogSentinel\Application\Contract\DocumentAnalyzerInterface;
use Gorshkov\CatalogSentinel\Domain\Analysis\AnalysisMetrics;
use Gorshkov\CatalogSentinel\Domain\Analysis\AnalysisResult;
use Gorshkov\CatalogSentinel\Domain\Analysis\DocumentKind;
use Gorshkov\CatalogSentinel\Domain\Policy\PolicySnapshot;
use InvalidArgumentException;
use RuntimeException;
use XMLReader;

final class XmlReaderCommerceMlAnalyzer implements DocumentAnalyzerInterface
{
    private const DEADLINE_CHECK_INTERVAL = 4096;

    public function __construct(
        private readonly int $softTimeLimitSeconds = 120,
        private readonly NumberParser $numberParser = new NumberParser(),
    ) {
    }

    public function analyze(string $path, PolicySnapshot $policy): AnalysisResult
    {
        $realPath = $this->validatePath($path);
        $startedAt = hrtime(true);
        $peakAtStart = memory_get_peak_usage(true);
        $samples = new SampleCollector($policy->sampleLimit);
        $accumulator = new MetricAccumulator($policy->protectedProperties, $samples);
        $reader = new XMLReader();
        $previousErrors = libxml_use_internal_errors(true);
        libxml_clear_errors();

        $flags = [
            'commerce' => false,
            'catalog' => false,
            'products' => false,
            'product' => false,
            'offers_package' => false,
            'offers' => false,
            'offer' => false,
            'classifier' => false,
        ];
        $sourceHints = [];
        $warnings = [];
        $valueErrors = [];
        $stack = [];
        $currentItem = null;
        $currentProperty = null;
        $propertyDepth = null;
        $ordinal = 0;
        $unsafe = false;
        $limitExceeded = false;
        $nodeCount = 0;
        $opened = false;
        $errors = [];

        try {
            $opened = $reader->open($realPath, null, LIBXML_NONET | LIBXML_COMPACT);
            if (!$opened) {
                throw new RuntimeException('XMLReader could not open the local file.');
            }
            $reader->setParserProperty(XMLReader::LOADDTD, false);
            $reader->setParserProperty(XMLReader::VALIDATE, false);
            $reader->setParserProperty(XMLReader::SUBST_ENTITIES, false);

            while ($reader->read()) {
                ++$nodeCount;
                if ($nodeCount % self::DEADLINE_CHECK_INTERVAL === 0) {
                    $elapsedSeconds = (hrtime(true) - $startedAt) / 1_000_000_000;
                    if ($elapsedSeconds > $this->softTimeLimitSeconds) {
                        $limitExceeded = true;
                        break;
                    }
                }
                if ($reader->nodeType === XMLReader::DOC_TYPE) {
                    $unsafe = true;
                    break;
                }
                if ($reader->nodeType === XMLReader::ELEMENT) {
                    $name = $reader->localName;
                    $depth = $reader->depth;
                    $stack[$depth] = $name;
                    foreach (array_keys($stack) as $knownDepth) {
                        if ($knownDepth > $depth) {
                            unset($stack[$knownDepth]);
                        }
                    }
                    $this->setStructureFlag($flags, $name);

                    if (($name === 'Товар' || $name === 'Предложение') && $currentItem === null) {
                        ++$ordinal;
                        $currentItem = new CurrentItemState($name, $ordinal, $depth);
                        if ($name === 'Товар') {
                            $flags['product'] = true;
                        } else {
                            $flags['offer'] = true;
                        }
                        continue;
                    }

                    if ($currentItem !== null && $name === 'ЗначенияСвойства') {
                        $currentProperty = new CurrentPropertyState();
                        $propertyDepth = $depth;
                        continue;
                    }

                    if ($currentItem !== null && ($name === 'Склад' || $name === 'Остаток')) {
                        $attribute = $reader->getAttribute('КоличествоНаСкладе') ?? $reader->getAttribute('Количество');
                        if ($attribute !== null) {
                            $this->addNumeric($attribute, $currentItem, $accumulator, true, false, $valueErrors);
                        }
                    }

                    if (!$reader->isEmptyElement && $this->isScalarElement($name)) {
                        $parent = $stack[$depth - 1] ?? '';
                        $value = $this->readScalar($reader, $depth);
                        if ($currentItem !== null) {
                            $this->consumeItemScalar(
                                $name,
                                $parent,
                                $stack,
                                $value,
                                $currentItem,
                                $currentProperty,
                                $accumulator,
                                $valueErrors,
                            );
                        } else {
                            $this->consumeSourceHint($name, $parent, $value, $sourceHints);
                        }
                    } elseif ($reader->isEmptyElement && $currentItem !== null && $this->isScalarElement($name)) {
                        $parent = $stack[$depth - 1] ?? '';
                        $this->consumeItemScalar(
                            $name,
                            $parent,
                            $stack,
                            '',
                            $currentItem,
                            $currentProperty,
                            $accumulator,
                            $valueErrors,
                        );
                    }
                } elseif ($reader->nodeType === XMLReader::END_ELEMENT) {
                    $name = $reader->localName;
                    if ($currentProperty !== null && $name === 'ЗначенияСвойства' && $reader->depth === $propertyDepth) {
                        $this->finishProperty($currentItem, $currentProperty, $policy->protectedProperties);
                        $currentProperty = null;
                        $propertyDepth = null;
                    }
                    if ($currentItem !== null && $name === $currentItem->kind && $reader->depth === $currentItem->depth) {
                        $accumulator->finish($currentItem);
                        $currentItem = null;
                    }
                    unset($stack[$reader->depth]);
                }
            }
        } finally {
            if ($opened) {
                $reader->close();
            }
            $errors = libxml_get_errors();
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrors);
        }
        $errorCode = null;
        if ($unsafe) {
            $errorCode = 'UNSAFE_XML';
        } elseif ($limitExceeded) {
            $errorCode = 'SCAN_LIMIT_EXCEEDED';
        } elseif ($errors !== []) {
            $errorCode = 'MALFORMED_XML';
        }
        if ($limitExceeded) {
            $warnings[] = 'scan.soft_time_limit_exceeded';
        }
        if ($errors !== []) {
            $warnings[] = 'xml.parse_error';
        }
        $warnings[] = 'stock_profile.warehouses_preferred';

        $durationMs = (int) round((hrtime(true) - $startedAt) / 1_000_000);
        $metrics = $accumulator->finalize((int) filesize($realPath), $durationMs);
        $documentKind = $this->classify($flags, $unsafe || $limitExceeded || $errors !== []);
        $wellFormed = !$unsafe && !$limitExceeded && $errors === [];

        return new AnalysisResult(
            $documentKind,
            DocumentKind::isSupported($documentKind),
            $wellFormed,
            new AnalysisMetrics($metrics),
            $samples->all(),
            $valueErrors,
            array_values(array_unique($warnings)),
            $sourceHints,
            $durationMs,
            max(0, memory_get_peak_usage(true) - $peakAtStart),
            [
                'file_name' => basename($realPath),
                'stock_profile' => 'warehouses_preferred',
                'analyzer_schema_version' => $policy->analyzerSchemaVersion,
                'peak_memory_bytes' => memory_get_peak_usage(true),
            ],
            $errorCode,
        );
    }

    private function validatePath(string $path): string
    {
        if ($path === '' || str_contains($path, "\0") || preg_match('#^[a-z][a-z0-9+.-]*://#i', $path) === 1) {
            throw new InvalidArgumentException('Only a local file path is allowed.');
        }
        $realPath = realpath($path);
        if ($realPath === false || !is_file($realPath) || !is_readable($realPath)) {
            throw new InvalidArgumentException('The XML file is unavailable.');
        }

        return $realPath;
    }

    /** @param array<string, bool> $flags */
    private function setStructureFlag(array &$flags, string $name): void
    {
        $map = [
            'КоммерческаяИнформация' => 'commerce',
            'Каталог' => 'catalog',
            'Товары' => 'products',
            'ПакетПредложений' => 'offers_package',
            'Предложения' => 'offers',
            'Классификатор' => 'classifier',
        ];
        if (isset($map[$name])) {
            $flags[$map[$name]] = true;
        }
    }

    private function isScalarElement(string $name): bool
    {
        return in_array($name, ['Ид', 'Наименование', 'Значение', 'Количество', 'КоличествоНаСкладе', 'ЦенаЗаЕдиницу'], true);
    }

    private function readScalar(XMLReader $reader, int $startDepth): string
    {
        $value = '';
        while ($reader->read()) {
            if (in_array($reader->nodeType, [XMLReader::TEXT, XMLReader::CDATA, XMLReader::SIGNIFICANT_WHITESPACE], true)) {
                $value .= $reader->value;
            } elseif ($reader->nodeType === XMLReader::END_ELEMENT && $reader->depth === $startDepth) {
                break;
            }
        }

        return $value;
    }

    /**
     * @param array<int, string> $stack
     * @param array<string, int> $valueErrors
     */
    private function consumeItemScalar(
        string $name,
        string $parent,
        array $stack,
        string $value,
        CurrentItemState $item,
        ?CurrentPropertyState $property,
        MetricAccumulator $accumulator,
        array &$valueErrors,
    ): void {
        if ($property !== null) {
            if ($name === 'Ид' && $parent === 'ЗначенияСвойства') {
                $property->externalId = trim($value);
            } elseif ($name === 'Значение') {
                $property->addValue($value);
            }

            return;
        }
        if ($name === 'Ид' && $parent === $item->kind) {
            $id = trim($value);
            $item->externalId = $id !== '' ? $id : null;

            return;
        }
        if ($name === 'Наименование' && $parent === $item->kind) {
            $displayName = trim($value);
            $item->displayName = $displayName !== '' ? $displayName : null;

            return;
        }
        if ($name === 'ЦенаЗаЕдиницу') {
            $this->addNumeric($value, $item, $accumulator, false, true, $valueErrors);

            return;
        }
        if ($name === 'КоличествоНаСкладе') {
            $this->addNumeric($value, $item, $accumulator, true, false, $valueErrors);

            return;
        }
        if ($name === 'Количество') {
            $warehouse = in_array('Склад', $stack, true) || in_array('Остаток', $stack, true);
            $this->addNumeric($value, $item, $accumulator, $warehouse, false, $valueErrors);
        }
    }

    /** @param array<string, int> $valueErrors */
    private function addNumeric(
        string $raw,
        CurrentItemState $item,
        MetricAccumulator $accumulator,
        bool $warehouse,
        bool $price,
        array &$valueErrors,
    ): void {
        $number = $this->numberParser->parse($raw);
        if ($number === null) {
            $valueErrors['invalid_numeric'] = ($valueErrors['invalid_numeric'] ?? 0) + 1;
            $accumulator->addValueError($item);

            return;
        }
        if ($price) {
            $item->priceValues[] = $number;
        } elseif ($warehouse) {
            $item->warehouseStockValues[] = $number;
        } else {
            $item->generalStockValues = [$number];
        }
    }

    /** @param list<string> $protectedProperties */
    private function finishProperty(
        ?CurrentItemState $item,
        CurrentPropertyState $property,
        array $protectedProperties,
    ): void {
        if ($item === null || $property->externalId === null) {
            return;
        }
        if (in_array($property->externalId, $protectedProperties, true)) {
            $item->properties[$property->externalId] = $property->hasNonEmptyValue;
        }
    }

    /** @param array<string, string> $sourceHints */
    private function consumeSourceHint(string $name, string $parent, string $value, array &$sourceHints): void
    {
        if ($name !== 'Ид' || !in_array($parent, ['Каталог', 'Классификатор', 'ПакетПредложений'], true)) {
            return;
        }
        $normalized = trim($value);
        if ($normalized !== '') {
            $sourceHints[strtolower($parent) . '_id'] = substr($normalized, 0, 200);
        }
    }

    /** @param array<string, bool> $flags */
    private function classify(array $flags, bool $malformed): string
    {
        if ($malformed) {
            return DocumentKind::MALFORMED;
        }
        if ($flags['offers_package'] && ($flags['offers'] || $flags['offer'])) {
            return DocumentKind::OFFERS;
        }
        if ($flags['catalog'] && ($flags['products'] || $flags['product'])) {
            return DocumentKind::CATALOG;
        }
        if ($flags['classifier'] && !$flags['catalog'] && !$flags['offers_package']) {
            return DocumentKind::CLASSIFIER;
        }
        if ($flags['commerce']) {
            return DocumentKind::UNKNOWN_COMMERCE_ML;
        }

        return DocumentKind::NOT_COMMERCE_ML;
    }
}
