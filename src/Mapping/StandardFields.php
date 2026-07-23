<?php

declare(strict_types=1);

namespace BricksGhlConnector\Mapping;

final class StandardFields
{
    /** @return array<string, string> */
    public static function options(): array
    {
        return [
            'firstName' => 'First name',
            'lastName' => 'Last name',
            'name' => 'Full name',
            'email' => 'Email',
            'phone' => 'Phone',
            'address1' => 'Address',
            'city' => 'City',
            'state' => 'State',
            'postalCode' => 'Postal code',
            'country' => 'Country',
            'companyName' => 'Company name',
            'website' => 'Website',
            'source' => 'Source',
        ];
    }

    public static function exists(string $field): bool
    {
        return array_key_exists($field, self::options());
    }
}
