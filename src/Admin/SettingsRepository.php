<?php

declare(strict_types=1);

namespace BricksGhlConnector\Admin;

use BricksGhlConnector\Support\Sanitizer;

final class SettingsRepository
{
    public const OPTION_NAME = 'bghl_connector_settings';
    public const DEFAULT_TRACKING_EVENT_NAME = 'ghl_lead';

    /** @return array<string, mixed> */
    public function all(): array
    {
        $settings = get_option(self::OPTION_NAME, []);

        if (! is_array($settings)) {
            $settings = [];
        }

        return array_merge($this->defaults(), $settings);
    }

    public function apiToken(): string
    {
        if (defined('BRICKS_GHL_CONNECTOR_API_TOKEN')) {
            return (string) constant('BRICKS_GHL_CONNECTOR_API_TOKEN');
        }

        return (string) $this->all()['api_token'];
    }

    public function locationId(): string
    {
        if (defined('BRICKS_GHL_CONNECTOR_LOCATION_ID')) {
            return (string) constant('BRICKS_GHL_CONNECTOR_LOCATION_ID');
        }

        return (string) $this->all()['location_id'];
    }

    public function defaultSource(): string
    {
        return (string) $this->all()['default_source'];
    }

    /** @return string[] */
    public function defaultTags(): array
    {
        return Sanitizer::csvToList((string) $this->all()['default_tags']);
    }

    public function isDebugEnabled(): bool
    {
        return (bool) $this->all()['debug_enabled'];
    }

    public function isTrackingEnabled(): bool
    {
        return (bool) $this->all()['tracking_enabled'];
    }

    public function trackingEventName(): string
    {
        $eventName = sanitize_key((string) $this->all()['tracking_event_name']);

        return $eventName !== '' ? $eventName : self::DEFAULT_TRACKING_EVENT_NAME;
    }

    /** @return array<string, mixed> */
    public function defaults(): array
    {
        return [
            'api_token' => '',
            'location_id' => '',
            'default_source' => 'Website Bricks Form',
            'default_tags' => 'website-form',
            'debug_enabled' => false,
            'tracking_enabled' => true,
            'tracking_event_name' => self::DEFAULT_TRACKING_EVENT_NAME,
        ];
    }

    /** @param array<string, mixed> $input */
    public function sanitize(array $input): array
    {
        $current = $this->all();
        $apiToken = isset($input['api_token']) ? trim((string) $input['api_token']) : '';

        if ($apiToken === '' && ! empty($current['api_token'])) {
            $apiToken = (string) $current['api_token'];
        }

        return [
            'api_token' => sanitize_text_field($apiToken),
            'location_id' => sanitize_text_field((string) ($input['location_id'] ?? '')),
            'default_source' => sanitize_text_field((string) ($input['default_source'] ?? 'Website Bricks Form')),
            'default_tags' => sanitize_text_field((string) ($input['default_tags'] ?? 'website-form')),
            'debug_enabled' => ! empty($input['debug_enabled']),
            'tracking_enabled' => ! empty($input['tracking_enabled']),
            'tracking_event_name' => $this->sanitizeEventName((string) ($input['tracking_event_name'] ?? '')),
        ];
    }

    private function sanitizeEventName(string $value): string
    {
        $eventName = sanitize_key($value);

        return $eventName !== '' ? $eventName : self::DEFAULT_TRACKING_EVENT_NAME;
    }

    public function isApiTokenDefinedInConfig(): bool
    {
        return defined('BRICKS_GHL_CONNECTOR_API_TOKEN');
    }

    public function isLocationIdDefinedInConfig(): bool
    {
        return defined('BRICKS_GHL_CONNECTOR_LOCATION_ID');
    }
}
