<?php

declare(strict_types=1);

namespace BricksGhlConnector\Mapping;

final class FieldMapper
{
    /**
     * @param array<string, mixed> $submittedFields
     * @param array<int, array<string, mixed>> $mappings
     * @return array{standard: array<string, string>, customFields: array<int, array{id: string, value: string}>}
     */
    public function map(array $submittedFields, array $mappings): array
    {
        $standard = [];
        $customFields = [];

        foreach ($mappings as $mapping) {
            $bricksFieldId = trim((string) ($mapping['bricksFieldId'] ?? ''));

            if ($bricksFieldId === '' || ! array_key_exists($bricksFieldId, $submittedFields)) {
                continue;
            }

            $value = $this->stringValue($submittedFields[$bricksFieldId]);

            if ($value === '') {
                continue;
            }

            if (($mapping['targetType'] ?? '') === 'standard') {
                $fieldName = (string) ($mapping['standardField'] ?? '');

                if (StandardFields::exists($fieldName)) {
                    $standard[$fieldName] = $value;
                }
            }

            if (($mapping['targetType'] ?? '') === 'custom') {
                $customFieldId = trim((string) ($mapping['customFieldId'] ?? ''));

                if ($customFieldId !== '') {
                    $customFields[] = [
                        'id' => $customFieldId,
                        'value' => $value,
                    ];
                }
            }
        }

        return [
            'standard' => $standard,
            'customFields' => $customFields,
        ];
    }

    /** @param mixed $value */
    private function stringValue($value): string
    {
        if (is_array($value) && array_key_exists('value', $value)) {
            $value = $value['value'];
        }

        if (is_array($value)) {
            $value = implode(', ', array_map(static fn ($item): string => trim((string) $item), $value));
        }

        return trim((string) $value);
    }
}
