<?php

declare(strict_types=1);

namespace Gorshkov\CatalogSentinel\Infrastructure\Support;

final class ByteSize
{
    public static function fromIni(string $value): int
    {
        $value = trim($value);
        if ($value === '' || $value === '-1') {
            return PHP_INT_MAX;
        }
        if (preg_match('/^(\d+)([KMG])?$/iD', $value, $matches) !== 1) {
            return 0;
        }
        $bytes = (int) $matches[1];
        $unit = strtoupper($matches[2] ?? '');
        $powers = ['' => 0, 'K' => 1, 'M' => 2, 'G' => 3];

        return $bytes * (1024 ** $powers[$unit]);
    }

    public static function uploadLimit(int $moduleLimit): int
    {
        return min($moduleLimit, self::fromIni((string) ini_get('upload_max_filesize')), self::fromIni((string) ini_get('post_max_size')));
    }
}
