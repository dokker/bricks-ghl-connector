<?php

declare(strict_types=1);

namespace BricksGhlConnector\Mapping;

final class MappingValidator
{
    /**
     * @param array<int, array<string, mixed>> $mappings
     * @return string[]
     */
    public function validate(array $mappings): array
    {
        $errors = [];
        $hasContactIdentifier = false;

        foreach ($mappings as $index => $mapping) {
            $targetType = (string) ($mapping['targetType'] ?? '');
            $bricksFieldId = trim((string) ($mapping['bricksFieldId'] ?? ''));

            if ($bricksFieldId === '') {
                $errors[] = sprintf('Mapping row %d is missing a Bricks field.', $index + 1);
                continue;
            }

            if ($targetType === 'standard') {
                $standardField = (string) ($mapping['standardField'] ?? '');

                if (! StandardFields::exists($standardField)) {
                    $errors[] = sprintf('Mapping row %d has an invalid GHL standard field.', $index + 1);
                }

                if (in_array($standardField, ['email', 'phone'], true)) {
                    $hasContactIdentifier = true;
                }
            } elseif ($targetType === 'custom') {
                if (trim((string) ($mapping['customFieldId'] ?? '')) === '') {
                    $errors[] = sprintf('Mapping row %d is missing a GHL custom field ID.', $index + 1);
                }
            } else {
                $errors[] = sprintf('Mapping row %d has an invalid target type.', $index + 1);
            }
        }

        if (! $hasContactIdentifier) {
            $errors[] = 'Map at least one Bricks field to the GHL email or phone field.';
        }

        return $errors;
    }
}
