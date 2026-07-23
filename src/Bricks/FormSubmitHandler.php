<?php

declare(strict_types=1);

namespace BricksGhlConnector\Bricks;

use BricksGhlConnector\Admin\SettingsRepository;
use BricksGhlConnector\GHL\ApiException;
use BricksGhlConnector\GHL\Client;
use BricksGhlConnector\GHL\ContactPayloadBuilder;
use BricksGhlConnector\Mapping\FieldMapper;
use BricksGhlConnector\Mapping\MappingValidator;
use BricksGhlConnector\Support\Logger;
use BricksGhlConnector\Support\Sanitizer;

final class FormSubmitHandler
{
    private SettingsRepository $settings;
    private MappingValidator $validator;
    private FieldMapper $mapper;
    private ContactPayloadBuilder $payloadBuilder;
    private Client $client;
    private Logger $logger;

    public function __construct(
        SettingsRepository $settings,
        MappingValidator $validator,
        FieldMapper $mapper,
        ContactPayloadBuilder $payloadBuilder,
        Client $client,
        Logger $logger
    ) {
        $this->settings = $settings;
        $this->validator = $validator;
        $this->mapper = $mapper;
        $this->payloadBuilder = $payloadBuilder;
        $this->client = $client;
        $this->logger = $logger;
    }

    /** @param object $form */
    public function handle($form): void
    {
        $formSettings = $this->callFormMethod($form, 'get_settings');
        $submittedFields = $this->callFormMethod($form, 'get_fields');

        if (! is_array($formSettings)) {
            $formSettings = [];
        }

        if (! is_array($submittedFields)) {
            $submittedFields = [];
        }

        $mappings = $this->sanitizeMappings($formSettings['ghlFieldMappings'] ?? []);
        $errors = $this->validator->validate($mappings);
        $locationId = $this->settings->locationId();

        if ($this->settings->apiToken() === '') {
            $errors[] = 'GHL API token is not configured.';
        }

        if ($locationId === '') {
            $errors[] = 'GHL Location ID is not configured.';
        }

        if ($errors !== []) {
            $this->logger->error('GHL form action validation failed.', ['errors' => $errors]);
            $this->maybeSetFormError($form, $formSettings, implode(' ', $errors));

            return;
        }

        $source = trim((string) ($formSettings['ghlSource'] ?? ''));
        $source = $source !== '' ? $source : $this->settings->defaultSource();
        $tags = array_merge(
            $this->settings->defaultTags(),
            Sanitizer::csvToList((string) ($formSettings['ghlTags'] ?? ''))
        );

        $mapped = $this->mapper->map($submittedFields, $mappings);
        $payloadErrors = $this->validateMappedContactFields($mapped['standard']);

        if ($payloadErrors !== []) {
            $this->logger->error('GHL form action mapped payload validation failed.', [
                'errors' => $payloadErrors,
                'submitted_field_keys' => array_keys($submittedFields),
                'mapped_standard_fields' => array_keys($mapped['standard']),
            ]);
            $this->maybeSetFormError($form, $formSettings, implode(' ', $payloadErrors));

            return;
        }

        $payload = $this->payloadBuilder->build($locationId, $source, $tags, $mapped);

        try {
            $this->client->createContact($payload);
            $this->logger->debug('GHL contact created from Bricks form.', [
                'mapped_standard_fields' => array_keys($mapped['standard']),
                'custom_field_count' => count($mapped['customFields']),
            ]);
        } catch (ApiException $exception) {
            $this->logger->error('GHL contact creation failed.', ['message' => $exception->getMessage()]);
            $this->maybeSetFormError($form, $formSettings, 'Could not submit the form to GoHighLevel.');
        }
    }

    /**
     * @param array<string, string> $standardFields
     * @return string[]
     */
    private function validateMappedContactFields(array $standardFields): array
    {
        $hasEmail = ! empty($standardFields['email']);
        $hasPhone = ! empty($standardFields['phone']);
        $hasName = ! empty($standardFields['name']);
        $hasFirstAndLastName = ! empty($standardFields['firstName']) && ! empty($standardFields['lastName']);

        if ($hasEmail || $hasPhone || $hasName || $hasFirstAndLastName) {
            return [];
        }

        return ['The GHL payload must contain a submitted email, phone, full name, or both first and last name.'];
    }

    /**
     * @param mixed $value
     * @return array<int, array<string, string>>
     */
    private function sanitizeMappings($value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $mappings = [];

        foreach ($value as $row) {
            if (! is_array($row)) {
                continue;
            }

            $mappings[] = [
                'bricksFieldId' => sanitize_text_field((string) ($row['bricksFieldId'] ?? '')),
                'targetType' => sanitize_key((string) ($row['targetType'] ?? 'standard')),
                'standardField' => sanitize_text_field((string) ($row['standardField'] ?? '')),
                'customFieldId' => sanitize_text_field((string) ($row['customFieldId'] ?? '')),
            ];
        }

        return $mappings;
    }

    /**
     * @param object $form
     * @return mixed
     */
    private function callFormMethod($form, string $method)
    {
        if (is_object($form) && method_exists($form, $method)) {
            return $form->{$method}();
        }

        return null;
    }

    /** @param array<string, mixed> $formSettings */
    private function maybeSetFormError($form, array $formSettings, string $message): void
    {
        if (($formSettings['ghlFailBehavior'] ?? 'block') === 'log') {
            return;
        }

        if (is_object($form) && method_exists($form, 'set_result')) {
            $form->set_result([
                'action' => 'ghl',
                'type' => 'error',
                'message' => esc_html($message),
            ]);
        }
    }
}
