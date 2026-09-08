<?php

declare(strict_types=1);

namespace BricksGhlConnector\Bricks;

use BricksGhlConnector\Admin\SettingsRepository;

/**
 * Bricks specific wiring for the dataLayer tracking.
 *
 * This is the replaceable adapter. It marks every form that uses the GHL action
 * with the tracking ID the site owner picked in the builder, and loads the
 * listener that turns a successful submission into a dataLayer push.
 */
final class TrackingScript
{
    private const SCRIPT_HANDLE = 'bghl-tracking';

    private SettingsRepository $settings;

    public function __construct(SettingsRepository $settings)
    {
        $this->settings = $settings;
    }

    public function register(): void
    {
        if (! $this->settings->isTrackingEnabled()) {
            return;
        }

        add_filter('bricks/element/set_root_attributes', [$this, 'addFormAttributes'], 10, 2);
        add_action('wp_enqueue_scripts', [$this, 'enqueueScript']);
    }

    /**
     * Adds the tracking markers to the root element of a GHL enabled form.
     *
     * Bricks passes the element object as the second argument, see
     * https://academy.bricksbuilder.io/article/filter-bricks-set_root_attributes/
     *
     * @param mixed $attributes
     * @param mixed $element
     * @return mixed
     */
    public function addFormAttributes($attributes, $element = null)
    {
        if (! is_array($attributes) || ! is_object($element)) {
            return $attributes;
        }

        if (! isset($element->name) || $element->name !== 'form') {
            return $attributes;
        }

        $settings = isset($element->settings) && is_array($element->settings) ? $element->settings : [];
        $actions = isset($settings['actions']) && is_array($settings['actions']) ? $settings['actions'] : [];

        if (! in_array('ghl', $actions, true)) {
            return $attributes;
        }

        $elementId = isset($element->id) ? sanitize_text_field((string) $element->id) : '';

        if ($elementId === '') {
            return $attributes;
        }

        $trackingId = sanitize_text_field(trim((string) ($settings['ghlTrackingId'] ?? '')));

        $attributes['data-bghl-form'] = $elementId;
        $attributes['data-bghl-tracking-id'] = $trackingId !== '' ? $trackingId : $elementId;

        return $attributes;
    }

    public function enqueueScript(): void
    {
        if (function_exists('bricks_is_builder') && bricks_is_builder()) {
            return;
        }

        wp_enqueue_script(
            self::SCRIPT_HANDLE,
            BGHL_CONNECTOR_URL . 'assets/js/tracking.js',
            [],
            BGHL_CONNECTOR_VERSION,
            true
        );

        wp_localize_script(
            self::SCRIPT_HANDLE,
            'bghlTracking',
            ['event' => $this->settings->trackingEventName()]
        );
    }
}
