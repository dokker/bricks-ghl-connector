<?php

declare(strict_types=1);

namespace BricksGhlConnector\Bricks;

use BricksGhlConnector\Mapping\StandardFields;

final class FormControls
{
    /** @param array<string, mixed> $controlGroups */
    public function addControlGroup(array $controlGroups): array
    {
        $controlGroups['ghl'] = [
            'title' => esc_html__('GHL', 'bricks-ghl-connector'),
            'tab' => 'content',
            'required' => ['actions', 'contains', 'ghl'],
        ];

        return $controlGroups;
    }

    /** @param array<string, mixed> $controls */
    public function addControls(array $controls): array
    {
        if (isset($controls['actions']['options']) && is_array($controls['actions']['options'])) {
            $controls['actions']['options']['ghl'] = esc_html__('GoHighLevel', 'bricks-ghl-connector');
        }

        $controls['ghlFieldMappings'] = [
            'tab' => 'content',
            'group' => 'ghl',
            'label' => esc_html__('Field mappings', 'bricks-ghl-connector'),
            'type' => 'repeater',
            'required' => ['actions', 'contains', 'ghl'],
            'fields' => [
                'bricksFieldId' => [
                    'label' => esc_html__('Bricks field', 'bricks-ghl-connector'),
                    'type' => 'select',
                    'map_fields' => true,
                ],
                'targetType' => [
                    'label' => esc_html__('GHL target type', 'bricks-ghl-connector'),
                    'type' => 'select',
                    'options' => [
                        'standard' => esc_html__('Standard field', 'bricks-ghl-connector'),
                        'custom' => esc_html__('Custom field', 'bricks-ghl-connector'),
                    ],
                    'default' => 'standard',
                ],
                'standardField' => [
                    'label' => esc_html__('GHL standard field', 'bricks-ghl-connector'),
                    'type' => 'select',
                    'options' => StandardFields::options(),
                    'required' => ['targetType', '=', 'standard'],
                ],
                'customFieldId' => [
                    'label' => esc_html__('GHL custom field ID', 'bricks-ghl-connector'),
                    'type' => 'text',
                    'required' => ['targetType', '=', 'custom'],
                ],
            ],
        ];

        $controls['ghlSource'] = [
            'tab' => 'content',
            'group' => 'ghl',
            'label' => esc_html__('Source override', 'bricks-ghl-connector'),
            'type' => 'text',
            'required' => ['actions', 'contains', 'ghl'],
        ];

        $controls['ghlTags'] = [
            'tab' => 'content',
            'group' => 'ghl',
            'label' => esc_html__('Additional tags', 'bricks-ghl-connector'),
            'type' => 'text',
            'placeholder' => 'website-form, landing-page',
            'required' => ['actions', 'contains', 'ghl'],
        ];

        $controls['ghlFailBehavior'] = [
            'tab' => 'content',
            'group' => 'ghl',
            'label' => esc_html__('On GHL error', 'bricks-ghl-connector'),
            'type' => 'select',
            'options' => [
                'block' => esc_html__('Show form error', 'bricks-ghl-connector'),
                'log' => esc_html__('Log only', 'bricks-ghl-connector'),
            ],
            'default' => 'block',
            'required' => ['actions', 'contains', 'ghl'],
        ];

        return $controls;
    }
}
