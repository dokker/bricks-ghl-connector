<?php

declare(strict_types=1);

namespace BricksGhlConnector\GHL;

final class NoteBuilder
{
    /**
     * Builds the contact note that stores the submitted message, so every
     * submission stays visible on the contact even when the contact already
     * existed.
     *
     * @param array<int, array{bricksFieldId: string, value: string}> $noteFields
     * @param array<string, string> $fieldLabels Bricks field ID => field label
     */
    public function build(string $source, array $noteFields, array $fieldLabels): string
    {
        if ($noteFields === []) {
            return '';
        }

        $sections = [sprintf(
            /* translators: %s: contact source, e.g. "Website Bricks Form" */
            __('Website form submission: %s', 'bricks-ghl-connector'),
            $source
        )];

        foreach ($noteFields as $noteField) {
            $fieldId = preg_replace('/^form-field-/', '', $noteField['bricksFieldId']);
            $label = $fieldLabels[$fieldId] ?? $fieldId;
            $sections[] = $label . ":\n" . $noteField['value'];
        }

        return implode("\n\n", $sections);
    }
}
