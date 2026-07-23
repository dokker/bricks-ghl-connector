<?php

declare(strict_types=1);

namespace BricksGhlConnector\Support;

final class Sanitizer
{
    /** @return string[] */
    public static function csvToList(string $value): array
    {
        $items = array_map('trim', explode(',', $value));
        $items = array_filter($items, static fn (string $item): bool => $item !== '');

        return array_values(array_unique($items));
    }
}
