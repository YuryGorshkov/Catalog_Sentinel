<?php

declare(strict_types=1);

namespace Gorshkov\CatalogSentinel\Infrastructure\Persistence;

final class JsonCodec
{
    /** @param array<mixed> $data */
    public function encode(array $data): string
    {
        return json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** @return array<mixed> */
    public function decode(?string $json): array
    {
        if ($json === null || $json === '') {
            return [];
        }
        $data = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
        if (!is_array($data)) {
            throw new \UnexpectedValueException('Expected a JSON object or array.');
        }

        return $data;
    }
}
