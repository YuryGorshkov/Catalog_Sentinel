<?php

declare(strict_types=1);

namespace Gorshkov\CatalogSentinel\Infrastructure\Xml;

final class NumberParser
{
    public function parse(string $raw): ?float
    {
        $value = preg_replace('/^[\s\x{00A0}]+|[\s\x{00A0}]+$/u', '', $raw);
        if ($value === null || $value === '') {
            return null;
        }
        if (str_contains($value, ',') && str_contains($value, '.')) {
            return null;
        }
        if (str_contains($value, ',')) {
            $value = str_replace(',', '.', $value);
        }
        if (preg_match('/^[+-]?(?:\d+(?:\.\d*)?|\.\d+)(?:[eE][+-]?\d+)?$/D', $value) !== 1) {
            return null;
        }

        $number = (float) $value;

        return is_finite($number) ? $number : null;
    }
}
