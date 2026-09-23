<?php

declare(strict_types=1);

namespace Gorshkov\CatalogSentinel\Infrastructure\Xml;

use Generator;
use InvalidArgumentException;
use RuntimeException;
use XMLReader;

/** Re-reads a local CommerceML file and yields matching products without retaining the full list. */
final class AffectedProductStreamer
{
    private const SUPPORTED_RULES = [
        'stock.zero_spike',
        'price.zero_spike',
        'property.empty_spike',
        'identifier.missing_ratio',
    ];

    public function __construct(private readonly NumberParser $numberParser = new NumberParser())
    {
    }

    /**
     * @param list<string> $ruleCodes
     * @param list<string> $propertyIds
     * @return Generator<int, array{rule_code: string, product_name: string, external_id: string, measured_value: string}>
     */
    public function rows(string $path, array $ruleCodes, array $propertyIds = []): Generator
    {
        $realPath = $this->validatePath($path);
        $rules = array_values(array_intersect(self::SUPPORTED_RULES, array_unique($ruleCodes)));
        $properties = array_fill_keys(array_values(array_unique($propertyIds)), true);
        $reader = new XMLReader();
        $previousErrors = libxml_use_internal_errors(true);
        libxml_clear_errors();
        $opened = false;
        $stack = [];
        $currentItem = null;
        $currentProperty = null;
        $propertyDepth = null;
        $ordinal = 0;

        try {
            $opened = $reader->open($realPath, null, LIBXML_NONET | LIBXML_COMPACT);
            if (!$opened) {
                throw new RuntimeException('XMLReader could not open the local file.');
            }
            $reader->setParserProperty(XMLReader::LOADDTD, false);
            $reader->setParserProperty(XMLReader::VALIDATE, false);
            $reader->setParserProperty(XMLReader::SUBST_ENTITIES, false);

            while ($reader->read()) {
                if ($reader->nodeType === XMLReader::DOC_TYPE) {
                    throw new RuntimeException('Unsafe XML document type declaration.');
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
                    if (($name === 'Товар' || $name === 'Предложение') && $currentItem === null) {
                        $currentItem = new CurrentItemState($name, ++$ordinal, $depth);
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
                            $this->addNumber($attribute, $currentItem, true, false);
                        }
                    }
                    if ($currentItem !== null && $this->isScalar($name)) {
                        $parent = $stack[$depth - 1] ?? '';
                        $value = $reader->isEmptyElement ? '' : $this->readScalar($reader, $depth);
                        $this->consumeScalar($name, $parent, $stack, $value, $currentItem, $currentProperty);
                    }
                } elseif ($reader->nodeType === XMLReader::END_ELEMENT) {
                    $name = $reader->localName;
                    if ($currentProperty !== null && $name === 'ЗначенияСвойства' && $reader->depth === $propertyDepth) {
                        if ($currentItem !== null && $currentProperty->externalId !== null && isset($properties[$currentProperty->externalId])) {
                            $currentItem->properties[$currentProperty->externalId] = $currentProperty->hasNonEmptyValue;
                        }
                        $currentProperty = null;
                        $propertyDepth = null;
                    }
                    if ($currentItem !== null && $name === $currentItem->kind && $reader->depth === $currentItem->depth) {
                        foreach ($this->matchingRows($currentItem, $rules, $properties) as $row) {
                            yield $row;
                        }
                        $currentItem = null;
                    }
                    unset($stack[$reader->depth]);
                }
            }

            if (libxml_get_errors() !== []) {
                throw new RuntimeException('Malformed XML document.');
            }
        } finally {
            if ($opened) {
                $reader->close();
            }
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrors);
        }
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

    private function isScalar(string $name): bool
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

    /** @param array<int, string> $stack */
    private function consumeScalar(
        string $name,
        string $parent,
        array $stack,
        string $value,
        CurrentItemState $item,
        ?CurrentPropertyState $property,
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
        } elseif ($name === 'Наименование' && $parent === $item->kind) {
            $displayName = trim($value);
            $item->displayName = $displayName !== '' ? $displayName : null;
        } elseif ($name === 'ЦенаЗаЕдиницу') {
            $this->addNumber($value, $item, false, true);
        } elseif ($name === 'КоличествоНаСкладе') {
            $this->addNumber($value, $item, true, false);
        } elseif ($name === 'Количество') {
            $warehouse = in_array('Склад', $stack, true) || in_array('Остаток', $stack, true);
            $this->addNumber($value, $item, $warehouse, false);
        }
    }

    private function addNumber(string $raw, CurrentItemState $item, bool $warehouse, bool $price): void
    {
        $number = $this->numberParser->parse($raw);
        if ($number === null) {
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

    /**
     * @param list<string> $rules
     * @param array<string, bool> $properties
     * @return Generator<int, array{rule_code: string, product_name: string, external_id: string, measured_value: string}>
     */
    private function matchingRows(CurrentItemState $item, array $rules, array $properties): Generator
    {
        $base = [
            'product_name' => $item->sampleLabel(),
            'external_id' => $item->sampleId(),
        ];
        if (in_array('stock.zero_spike', $rules, true)) {
            $values = $item->warehouseStockValues !== [] ? $item->warehouseStockValues : $item->generalStockValues;
            if ($values !== [] && array_sum($values) <= 0.0) {
                yield ['rule_code' => 'stock.zero_spike'] + $base + ['measured_value' => (string) array_sum($values)];
            }
        }
        if (in_array('price.zero_spike', $rules, true) && $item->priceValues !== []) {
            $hasPositive = array_filter($item->priceValues, static fn (float $value): bool => $value > 0.0) !== [];
            if (!$hasPositive) {
                yield ['rule_code' => 'price.zero_spike'] + $base + ['measured_value' => implode('|', $item->priceValues)];
            }
        }
        if (in_array('identifier.missing_ratio', $rules, true) && $item->externalId === null) {
            yield ['rule_code' => 'identifier.missing_ratio'] + $base + ['measured_value' => 'missing'];
        }
        if (in_array('property.empty_spike', $rules, true)) {
            foreach ($properties as $externalId => $_configured) {
                if (array_key_exists($externalId, $item->properties) && !$item->properties[$externalId]) {
                    yield ['rule_code' => 'property.empty_spike:' . $externalId] + $base + ['measured_value' => 'empty'];
                }
            }
        }
    }
}
