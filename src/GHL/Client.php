<?php

declare(strict_types=1);

namespace BricksGhlConnector\GHL;

use BricksGhlConnector\Admin\SettingsRepository;
use BricksGhlConnector\Support\Logger;

final class Client
{
    private const API_BASE = 'https://services.leadconnectorhq.com';
    private const API_VERSION = '2021-07-28';

    private SettingsRepository $settings;
    private Logger $logger;

    public function __construct(SettingsRepository $settings, Logger $logger)
    {
        $this->settings = $settings;
        $this->logger = $logger;
    }

    /** @param array<string, mixed> $payload */
    public function createContact(array $payload): array
    {
        $token = $this->settings->apiToken();

        if ($token === '') {
            throw new ApiException('GHL API token is missing.');
        }

        $response = wp_remote_post(
            self::API_BASE . '/contacts/',
            [
                'timeout' => 15,
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json',
                    'Version' => self::API_VERSION,
                ],
                'body' => wp_json_encode($payload),
            ]
        );

        if (is_wp_error($response)) {
            throw new ApiException($response->get_error_message());
        }

        $statusCode = (int) wp_remote_retrieve_response_code($response);
        $body = (string) wp_remote_retrieve_body($response);
        $decoded = json_decode($body, true);
        $decodedBody = is_array($decoded) ? $decoded : [];

        $this->logger->debug('GHL Contacts API response received.', [
            'status_code' => $statusCode,
            'body' => $decodedBody !== [] ? $decodedBody : $body,
        ]);

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new ApiException(sprintf('GHL Contacts API returned HTTP %d.', $statusCode), $statusCode, $decodedBody);
        }

        return $decodedBody;
    }
}
