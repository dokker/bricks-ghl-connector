<?php

declare(strict_types=1);

namespace BricksGhlConnector\Support;

use BricksGhlConnector\Admin\SettingsRepository;

final class Logger
{
    private SettingsRepository $settings;

    public function __construct(SettingsRepository $settings)
    {
        $this->settings = $settings;
    }

    /** @param array<string, mixed> $context */
    public function debug(string $message, array $context = []): void
    {
        if (! $this->settings->isDebugEnabled()) {
            return;
        }

        $this->log('debug', $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function error(string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    /** @param array<string, mixed> $context */
    private function log(string $level, string $message, array $context): void
    {
        $context = $this->redact($context);

        error_log(sprintf(
            '[bricks-ghl-connector] %s: %s %s',
            strtoupper($level),
            $message,
            $context === [] ? '' : wp_json_encode($context)
        ));
    }

    /** @param array<string, mixed> $context */
    private function redact(array $context): array
    {
        foreach (['api_token', 'token', 'authorization', 'Authorization'] as $key) {
            if (array_key_exists($key, $context)) {
                $context[$key] = '[redacted]';
            }
        }

        return $context;
    }
}
