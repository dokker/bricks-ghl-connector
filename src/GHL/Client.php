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

    /**
     * Creates the contact, or updates the existing one when the location's
     * duplicate contact setting finds a match on email or phone.
     *
     * Do not send tags here: upsert overwrites every existing tag on the
     * contact. Use addTags() instead.
     *
     * @param array<string, mixed> $payload
     * @return array{id: string, new: bool}
     */
    public function upsertContact(array $payload): array
    {
        $body = $this->post('/contacts/upsert', $payload);
        $contactId = (string) ($body['contact']['id'] ?? '');

        if ($contactId === '') {
            throw new ApiException('GHL Contacts API response did not contain a contact ID.', 0, $body);
        }

        return [
            'id' => $contactId,
            'new' => (bool) ($body['new'] ?? false),
        ];
    }

    /**
     * Adds tags without removing the ones the contact already has.
     *
     * @param string[] $tags
     */
    public function addTags(string $contactId, array $tags): void
    {
        $this->post('/contacts/' . rawurlencode($contactId) . '/tags', ['tags' => array_values($tags)]);
    }

    public function createNote(string $contactId, string $body): void
    {
        $this->post('/contacts/' . rawurlencode($contactId) . '/notes', ['body' => $body]);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function post(string $path, array $payload): array
    {
        $token = $this->settings->apiToken();

        if ($token === '') {
            throw new ApiException('GHL API token is missing.');
        }

        $response = wp_remote_post(
            self::API_BASE . $path,
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

        $this->logger->debug('GHL API response received.', [
            'path' => $path,
            'status_code' => $statusCode,
            'body' => $decodedBody !== [] ? $decodedBody : $body,
        ]);

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new ApiException(sprintf('GHL API %s returned HTTP %d.', $path, $statusCode), $statusCode, $decodedBody);
        }

        return $decodedBody;
    }
}
