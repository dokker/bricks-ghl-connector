<?php

declare(strict_types=1);

namespace BricksGhlConnector\GHL;

final class ContactPayloadBuilder
{
    /**
     * @param array{standard: array<string, string>, customFields: array<int, array{id: string, value: string}>} $mapped
     * @param string[] $tags
     * @return array<string, mixed>
     */
    public function build(string $locationId, string $source, array $tags, array $mapped): array
    {
        $payload = array_merge(
            [
                'locationId' => $locationId,
                'source' => $source,
                'tags' => array_values(array_unique($tags)),
            ],
            $mapped['standard']
        );

        if ($mapped['customFields'] !== []) {
            $payload['customFields'] = $mapped['customFields'];
        }

        return array_filter(
            $payload,
            static fn ($value): bool => $value !== '' && $value !== []
        );
    }
}
