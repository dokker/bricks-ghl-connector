<?php

declare(strict_types=1);

namespace BricksGhlConnector\Mapping;

use BricksGhlConnector\Admin\SettingsRepository;
use BricksGhlConnector\Support\DateNormalizer;
use BricksGhlConnector\Support\PhoneNormalizer;

/**
 * Cleans mapped standard field values before they go into the upsert payload.
 * Every step is off or a no-op by default, so a site that does not change the
 * settings sends exactly what the visitor typed.
 */
final class ContactFieldNormalizer
{
    private PhoneNormalizer $phoneNormalizer;
    private DateNormalizer $dateNormalizer;

    public function __construct(PhoneNormalizer $phoneNormalizer, DateNormalizer $dateNormalizer)
    {
        $this->phoneNormalizer = $phoneNormalizer;
        $this->dateNormalizer = $dateNormalizer;
    }

    /**
     * @param array<string, string> $standard
     * @return array{standard: array<string, string>, warnings: array<int, array{message: string, field: string}>}
     */
    public function normalize(array $standard, string $phoneCountry, string $nameOrder): array
    {
        $warnings = [];

        if (isset($standard['dateOfBirth'])) {
            $date = $this->dateNormalizer->normalize($standard['dateOfBirth']);

            if ($date !== null) {
                $standard['dateOfBirth'] = $date;
            } else {
                // GHL rejects the whole request for an invalid date, so drop the field instead.
                unset($standard['dateOfBirth']);
                $warnings[] = [
                    'message' => 'Submitted date of birth could not be parsed and was not sent to GHL.',
                    'field' => 'dateOfBirth',
                ];
            }
        }

        if (isset($standard['phone']) && $phoneCountry !== '') {
            $phone = $this->phoneNormalizer->normalize($standard['phone'], $phoneCountry);

            if ($phone !== null) {
                $standard['phone'] = $phone;
            } else {
                // The phone may be the only contact identifier, so keep the raw value.
                $warnings[] = [
                    'message' => sprintf('Submitted phone number is not a valid %s number and was sent unchanged.', $phoneCountry),
                    'field' => 'phone',
                ];
            }
        }

        if (
            $nameOrder === SettingsRepository::NAME_ORDER_FAMILY_FIRST
            && isset($standard['name'])
            && ! isset($standard['firstName'])
            && ! isset($standard['lastName'])
        ) {
            $standard = array_merge($standard, $this->splitFamilyFirst($standard['name']));
        }

        return [
            'standard' => $standard,
            'warnings' => $warnings,
        ];
    }

    /**
     * "Nagy Dénes" => lastName "Nagy", firstName "Dénes". The last word is the
     * given name, everything before it the family name. A single word is
     * treated as the family name.
     *
     * @return array<string, string>
     */
    public function splitFamilyFirst(string $fullName): array
    {
        $words = preg_split('/\s+/u', trim($fullName), -1, PREG_SPLIT_NO_EMPTY);

        if (! is_array($words) || $words === []) {
            return [];
        }

        if (count($words) === 1) {
            return ['lastName' => $words[0]];
        }

        $firstName = (string) array_pop($words);

        return [
            'firstName' => $firstName,
            'lastName' => implode(' ', $words),
        ];
    }
}
