<?php

declare(strict_types=1);

namespace BricksGhlConnector\Bricks;

use BricksGhlConnector\Admin\SettingsRepository;
use BricksGhlConnector\GHL\ApiException;
use BricksGhlConnector\GHL\Client;
use BricksGhlConnector\GHL\ContactPayloadBuilder;
use BricksGhlConnector\GHL\NoteBuilder;
use BricksGhlConnector\Mapping\ContactFieldNormalizer;
use BricksGhlConnector\Mapping\FieldMapper;
use BricksGhlConnector\Mapping\MappingValidator;
use BricksGhlConnector\Support\Logger;
use BricksGhlConnector\Support\Sanitizer;

final class FormSubmitHandler
{
    private SettingsRepository $settings;
    private MappingValidator $validator;
    private FieldMapper $mapper;
    private ContactFieldNormalizer $normalizer;
    private ContactPayloadBuilder $payloadBuilder;
    private NoteBuilder $noteBuilder;
    private Client $client;
    private Logger $logger;

    public function __construct(
        SettingsRepository $settings,
        MappingValidator $validator,
        FieldMapper $mapper,
        ContactFieldNormalizer $normalizer,
        ContactPayloadBuilder $payloadBuilder,
        NoteBuilder $noteBuilder,
        Client $client,
        Logger $logger
    ) {
        $this->settings = $settings;
        $this->validator = $validator;
        $this->mapper = $mapper;
        $this->normalizer = $normalizer;
        $this->payloadBuilder = $payloadBuilder;
        $this->noteBuilder = $noteBuilder;
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

        $result = [
            'status' => 'failed',
            'contact_id' => null,
            'is_new' => null,
            'failed_step' => null,
            'error' => null,
            'tracking_id' => $this->trackingId($form, $formSettings),
            'submitted_at' => gmdate('c'),
        ];

        $mappings = $this->sanitizeMappings($formSettings['ghlFieldMappings'] ?? []);
        $errors = $this->validator->validate($mappings);
        $locationId = $this->settings->locationId();

        if ($this->settings->apiToken() === '') {
            $errors[] = __('GHL API token is not configured.', 'bricks-ghl-connector');
        }

        if ($locationId === '') {
            $errors[] = __('GHL Location ID is not configured.', 'bricks-ghl-connector');
        }

        if ($errors !== []) {
            $this->logger->error('GHL form action validation failed.', ['errors' => $errors]);
            $this->maybeSetFormError($form, $formSettings, implode(' ', $errors));
            $this->afterSubmit(array_merge($result, [
                'failed_step' => 'validation',
                'error' => implode(' ', $errors),
            ]), $form);

            return;
        }

        $source = trim((string) ($formSettings['ghlSource'] ?? ''));
        $source = $source !== '' ? $source : $this->settings->defaultSource();
        $tags = array_merge(
            $this->settings->defaultTags(),
            Sanitizer::csvToList((string) ($formSettings['ghlTags'] ?? ''))
        );

        $mapped = $this->mapper->map($submittedFields, $mappings);
        $normalized = $this->normalizer->normalize(
            $mapped['standard'],
            $this->settings->phoneDefaultCountry(),
            $this->settings->nameOrder()
        );
        $mapped['standard'] = $normalized['standard'];

        foreach ($normalized['warnings'] as $warning) {
            $this->logger->warning($warning['message'], ['field' => $warning['field']]);
        }

        $payloadErrors = $this->validateMappedContactFields($mapped['standard']);

        if ($payloadErrors !== []) {
            $this->logger->error('GHL form action mapped payload validation failed.', [
                'errors' => $payloadErrors,
                'submitted_field_keys' => array_keys($submittedFields),
                'mapped_standard_fields' => array_keys($mapped['standard']),
            ]);
            $this->maybeSetFormError($form, $formSettings, implode(' ', $payloadErrors));
            // The configuration is fine, the visitor just did not submit a contact identifier.
            $this->afterSubmit(array_merge($result, [
                'status' => 'skipped',
                'failed_step' => 'validation',
                'error' => implode(' ', $payloadErrors),
            ]), $form);

            return;
        }

        $payload = $this->payloadBuilder->build($locationId, $source, $mapped);

        /**
         * Filters the contact upsert payload right before it is sent to GHL.
         * A "tags" key is removed afterwards, because upsert would overwrite the
         * contact's existing tags.
         *
         * @param array<string, mixed> $payload
         * @param object $form Bricks form instance.
         */
        $filtered = apply_filters('bghl_connector/contact_payload', $payload, $form);
        $payload = is_array($filtered) ? $filtered : $payload;
        unset($payload['tags']);

        try {
            $contact = $this->client->upsertContact($payload);
            $this->logger->debug('GHL contact upserted from Bricks form.', [
                'new_contact' => $contact['new'],
                'mapped_standard_fields' => array_keys($mapped['standard']),
                'custom_field_count' => count($mapped['customFields']),
            ]);
        } catch (ApiException $exception) {
            $this->logger->error('GHL contact upsert failed.', ['message' => $exception->getMessage()]);
            $this->maybeSetFormError($form, $formSettings, __('Could not submit the form to GoHighLevel.', 'bricks-ghl-connector'));
            $this->afterSubmit(array_merge($result, [
                'failed_step' => 'upsert',
                'error' => $exception->getMessage(),
            ]), $form);

            return;
        }

        // The contact exists from here on, so the status stays "success" and a
        // later failure is only reported through failed_step.
        $result = array_merge($result, [
            'status' => 'success',
            'contact_id' => $contact['id'],
            'is_new' => $contact['new'],
        ]);

        $tags = array_values(array_unique($tags));

        if ($tags !== []) {
            try {
                $this->client->addTags($contact['id'], $tags);
            } catch (ApiException $exception) {
                // The contact and the message are what matter, a missing tag is not worth failing the form.
                $this->logger->error('GHL tag assignment failed.', ['message' => $exception->getMessage()]);
                $result['failed_step'] = 'tags';
                $result['error'] = $exception->getMessage();
            }
        }

        $note = $this->noteBuilder->build($source, $mapped['notes'], $this->fieldLabels($formSettings));

        if ($note !== '') {
            try {
                $this->client->createNote($contact['id'], $note);
            } catch (ApiException $exception) {
                $this->logger->error('GHL note creation failed.', ['message' => $exception->getMessage()]);
                $this->maybeSetFormError($form, $formSettings, __('Could not submit the form to GoHighLevel.', 'bricks-ghl-connector'));
                $result['failed_step'] = 'note';
                $result['error'] = $exception->getMessage();
            }
        }

        $this->afterSubmit($result, $form);
    }

    /**
     * Reports the outcome of every submission, so another plugin can store the
     * GHL status and contact ID next to its own copy of the submission.
     *
     * @param array{status: string, contact_id: ?string, is_new: ?bool, failed_step: ?string, error: ?string, tracking_id: string, submitted_at: string} $result
     * @param object $form
     */
    private function afterSubmit(array $result, $form): void
    {
        do_action('bghl_connector/after_submit', $result, $form);
    }

    /**
     * Same value the tracking script pushes as form_id: the per-form tracking
     * ID, or the Bricks element ID when it is empty.
     *
     * @param object $form
     * @param array<string, mixed> $formSettings
     */
    private function trackingId($form, array $formSettings): string
    {
        $trackingId = sanitize_text_field(trim((string) ($formSettings['ghlTrackingId'] ?? '')));

        if ($trackingId !== '') {
            return $trackingId;
        }

        $elementId = $this->callFormMethod($form, 'get_id');

        return is_scalar($elementId) ? sanitize_text_field((string) $elementId) : '';
    }

    /**
     * Bricks field ID => label, taken from the form element's field list.
     *
     * @param array<string, mixed> $formSettings
     * @return array<string, string>
     */
    private function fieldLabels(array $formSettings): array
    {
        $labels = [];
        $fields = $formSettings['fields'] ?? [];

        if (! is_array($fields)) {
            return [];
        }

        foreach ($fields as $field) {
            if (! is_array($field) || empty($field['id'])) {
                continue;
            }

            $label = trim(wp_strip_all_tags((string) ($field['label'] ?? '')));
            $labels[(string) $field['id']] = $label !== '' ? $label : (string) $field['id'];
        }

        return $labels;
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

        return [__('The GHL payload must contain a submitted email, phone, full name, or both first and last name.', 'bricks-ghl-connector')];
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
