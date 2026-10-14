<?php

declare(strict_types=1);

namespace BricksGhlConnector\GHL;

final class ContactPayloadBuilder
{
    /**
     * @param array{standard: array<string, string>, customFields: array<int, array{id: string, value: string}>, notes: array<int, array{bricksFieldId: string, value: string}>} $mapped
     * Tags are left out on purpose, because the upsert endpoint replaces every
     * existing tag on the contact. They are added in a separate request.
     *
     * @return array<string, mixed>
     */
    public function build(string $locationId, string $source, array $mapped): array
    {
        $payload = array_merge(
            [
                'locationId' => $locationId,
                'source' => $source,
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
