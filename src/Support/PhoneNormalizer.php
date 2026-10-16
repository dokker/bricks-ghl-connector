<?php

declare(strict_types=1);

namespace BricksGhlConnector\Support;

/**
 * Turns phone numbers typed in a national format into E.164, so the GHL upsert
 * matches "06 30 123 4567" and "+36301234567" to the same contact.
 *
 * Each supported country has a calling code and a national rule. Add a country
 * to COUNTRIES and give it a case in nationalNumber() and validNational().
 */
final class PhoneNormalizer
{
    /** @var array<string, array{label: string, callingCode: string}> */
    private const COUNTRIES = [
        'HU' => [
            'label' => 'Hungary (+36)',
            'callingCode' => '36',
        ],
    ];

    /**
     * Supported default countries as select options: ISO code => label.
     *
     * @return array<string, string>
     */
    public static function countries(): array
    {
        return array_map(static fn (array $country): string => $country['label'], self::COUNTRIES);
    }

    public static function supports(string $country): bool
    {
        return array_key_exists($country, self::COUNTRIES);
    }

    /**
     * Returns the number in E.164 form, or null when it is not a valid number
     * for the default country. The caller decides what to do with null.
     */
    public function normalize(string $raw, string $country): ?string
    {
        if (! self::supports($country)) {
            return null;
        }

        $number = preg_replace('/[\s\-()\/]+/u', '', trim($raw));

        if (! is_string($number) || $number === '') {
            return null;
        }

        if (strpos($number, '00') === 0) {
            $number = '+' . substr($number, 2);
        }

        $callingCode = self::COUNTRIES[$country]['callingCode'];

        if (strpos($number, '+') === 0) {
            $digits = substr($number, 1);

            if (! ctype_digit($digits)) {
                return null;
            }

            if (strpos($digits, $callingCode) === 0) {
                return $this->validNational($country, substr($digits, strlen($callingCode)))
                    ? $number
                    : null;
            }

            // A foreign number in international form: only check the E.164 length.
            return strlen($digits) >= 8 && strlen($digits) <= 15 ? $number : null;
        }

        if (! ctype_digit($number)) {
            return null;
        }

        $national = $this->nationalNumber($country, $number);

        if ($national === null || ! $this->validNational($country, $national)) {
            return null;
        }

        return '+' . $callingCode . $national;
    }

    /** The subscriber part without the trunk prefix, or null when the input does not fit. */
    private function nationalNumber(string $country, string $digits): ?string
    {
        switch ($country) {
            case 'HU':
                return $this->nationalHu($digits);
        }

        return null;
    }

    private function validNational(string $country, string $national): bool
    {
        switch ($country) {
            case 'HU':
                return $this->validHu($national);
        }

        return false;
    }

    /**
     * Strips the Hungarian trunk prefix "06", or the calling code "36" typed
     * without the plus sign. A number without either is accepted only when it
     * is 9 digits long. "36" is also the Eger area code, but an Eger number
     * without a prefix is 8 digits, so it never reaches the 10-11 digit case.
     */
    private function nationalHu(string $digits): ?string
    {
        $length = strlen($digits);

        if (strpos($digits, '06') === 0 && $length >= 10 && $length <= 11) {
            return substr($digits, 2);
        }

        if (strpos($digits, '36') === 0 && $length >= 10 && $length <= 11) {
            return substr($digits, 2);
        }

        if ($length === 9 && $digits[0] !== '0') {
            return $digits;
        }

        return null;
    }

    /**
     * The Hungarian subscriber part after +36: 9 digits after a mobile prefix
     * (20, 30, 31, 50, 70) or the nomadic prefix 21, 8 digits after any other
     * area code. A mobile number one digit short is therefore rejected.
     */
    private function validHu(string $national): bool
    {
        if (preg_match('/^(20|21|30|31|50|70)/', $national) === 1) {
            return preg_match('/^\d{9}$/', $national) === 1;
        }

        return preg_match('/^[1-9]\d{7}$/', $national) === 1;
    }
}
