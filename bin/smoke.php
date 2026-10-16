<?php
/**
 * Offline smoke check for the field normalization and the contact payload.
 * It calls plain functions only and never talks to GHL.
 *
 * Run from the WordPress root:
 *   wp eval-file wp-content/plugins/bricks-ghl-connector/bin/smoke.php
 *
 * Not part of the release ZIP (see .gitattributes). There is no strict_types
 * declaration, because wp eval-file runs the file through eval().
 */

use BricksGhlConnector\Admin\SettingsRepository;
use BricksGhlConnector\GHL\ContactPayloadBuilder;
use BricksGhlConnector\Mapping\ContactFieldNormalizer;
use BricksGhlConnector\Mapping\FieldMapper;
use BricksGhlConnector\Support\DateNormalizer;
use BricksGhlConnector\Support\PhoneNormalizer;

if (! defined('ABSPATH')) {
    fwrite(STDERR, "Run this file with wp eval-file.\n");
    exit(1);
}

// Works with the plugin active or not: load only the classes that are missing.
$bghlSmokeClasses = [
    'BricksGhlConnector\\Support\\Sanitizer' => 'src/Support/Sanitizer.php',
    'BricksGhlConnector\\Support\\PhoneNormalizer' => 'src/Support/PhoneNormalizer.php',
    'BricksGhlConnector\\Support\\DateNormalizer' => 'src/Support/DateNormalizer.php',
    'BricksGhlConnector\\Admin\\SettingsRepository' => 'src/Admin/SettingsRepository.php',
    'BricksGhlConnector\\Mapping\\StandardFields' => 'src/Mapping/StandardFields.php',
    'BricksGhlConnector\\Mapping\\FieldMapper' => 'src/Mapping/FieldMapper.php',
    'BricksGhlConnector\\Mapping\\ContactFieldNormalizer' => 'src/Mapping/ContactFieldNormalizer.php',
    'BricksGhlConnector\\GHL\\ContactPayloadBuilder' => 'src/GHL/ContactPayloadBuilder.php',
];

foreach ($bghlSmokeClasses as $class => $file) {
    if (! class_exists($class)) {
        require_once dirname(__DIR__) . '/' . $file;
    }
}

$bghlSmokeFailures = 0;

/**
 * @param mixed $expected
 * @param mixed $actual
 */
$check = static function (string $label, $expected, $actual) use (&$bghlSmokeFailures): void {
    $pass = $expected === $actual;

    if (! $pass) {
        $bghlSmokeFailures++;
    }

    echo sprintf(
        "%s  %s\n      expected: %s\n      actual:   %s\n",
        $pass ? 'PASS' : 'FAIL',
        $label,
        json_encode($expected, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        json_encode($actual, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    );
};

$normalizer = new ContactFieldNormalizer(new PhoneNormalizer(), new DateNormalizer());
$givenFirst = SettingsRepository::NAME_ORDER_GIVEN_FIRST;
$familyFirst = SettingsRepository::NAME_ORDER_FAMILY_FIRST;
$warningFields = static fn (array $result): array => array_column($result['warnings'], 'field');

echo "== Phone (phone_default_country = HU)\n";

$phoneCases = [
    '06 30 123 4567' => ['+36301234567', []],
    '+36-30-123-4567' => ['+36301234567', []],
    '0036301234567' => ['+36301234567', []],
    '301234567' => ['+36301234567', []],
    '36 30 123 4567' => ['+36301234567', []],
    '36301234567' => ['+36301234567', []],
    '(06-1) 234-5678' => ['+3612345678', []],
    '36 1 234 5678' => ['+3612345678', []],
    '06 36 123 456' => ['+3636123456', []],
    '06 21 234 5678' => ['+36212345678', []],
    '+44 20 7946 0958' => ['+442079460958', []],
    '12345' => ['12345', ['phone']],
    '+36 30 12' => ['+36 30 12', ['phone']],
    '06 30 123 456' => ['06 30 123 456', ['phone']],
    '+3630123456' => ['+3630123456', ['phone']],
    '36 30 123 456' => ['36 30 123 456', ['phone']],
    '36123456' => ['36123456', ['phone']],
    '06 1 234 56789' => ['06 1 234 56789', ['phone']],
];

foreach ($phoneCases as $input => [$expectedPhone, $expectedWarnings]) {
    // PHP turns the numeric key "301234567" into an int.
    $input = (string) $input;
    $result = $normalizer->normalize(['phone' => $input], 'HU', $givenFirst);
    $check(
        sprintf('phone "%s"', $input),
        ['phone' => $expectedPhone, 'warnings' => $expectedWarnings],
        ['phone' => $result['standard']['phone'] ?? null, 'warnings' => $warningFields($result)]
    );
}

$result = $normalizer->normalize(['phone' => '06 30 123 4567'], '', $givenFirst);
$check(
    'phone "06 30 123 4567" with normalization off (default)',
    ['phone' => '06 30 123 4567', 'warnings' => []],
    ['phone' => $result['standard']['phone'], 'warnings' => $warningFields($result)]
);

echo "\n== Date of birth\n";

$dateCases = [
    '2005-03-14' => ['2005-03-14', []],
    '2005.03.14.' => ['2005-03-14', []],
    '2005. 03. 14.' => ['2005-03-14', []],
    '2005/03/14' => ['2005-03-14', []],
    '14.03.2005' => ['2005-03-14', []],
    '14/03/2005' => ['2005-03-14', []],
    '2005-02-30' => [null, ['dateOfBirth']],
    '14.03.05' => [null, ['dateOfBirth']],
    'abc' => [null, ['dateOfBirth']],
];

foreach ($dateCases as $input => [$expectedDate, $expectedWarnings]) {
    $result = $normalizer->normalize(['email' => 'a@example.com', 'dateOfBirth' => $input], '', $givenFirst);
    $check(
        sprintf('dateOfBirth "%s"', $input),
        ['dateOfBirth' => $expectedDate, 'warnings' => $expectedWarnings, 'email kept' => true],
        [
            'dateOfBirth' => $result['standard']['dateOfBirth'] ?? null,
            'warnings' => $warningFields($result),
            'email kept' => ($result['standard']['email'] ?? '') === 'a@example.com',
        ]
    );
}

echo "\n== Full name\n";

$nameCases = [
    'Nagy Dénes' => [
        $givenFirst => ['name' => 'Nagy Dénes'],
        $familyFirst => ['name' => 'Nagy Dénes', 'firstName' => 'Dénes', 'lastName' => 'Nagy'],
    ],
    'Kovácsné Szabó Anna' => [
        $givenFirst => ['name' => 'Kovácsné Szabó Anna'],
        $familyFirst => ['name' => 'Kovácsné Szabó Anna', 'firstName' => 'Anna', 'lastName' => 'Kovácsné Szabó'],
    ],
    'Péter' => [
        $givenFirst => ['name' => 'Péter'],
        $familyFirst => ['name' => 'Péter', 'lastName' => 'Péter'],
    ],
];

foreach ($nameCases as $input => $byOrder) {
    foreach ($byOrder as $order => $expected) {
        $result = $normalizer->normalize(['name' => $input], '', $order);
        $check(sprintf('name "%s" (%s)', $input, $order), $expected, $result['standard']);
    }
}

$result = $normalizer->normalize(['name' => 'Nagy Dénes', 'firstName' => 'Dénes'], '', $familyFirst);
$check(
    'name is not split when firstName is mapped too',
    ['name' => 'Nagy Dénes', 'firstName' => 'Dénes'],
    $result['standard']
);

echo "\n== Contact payload (mapper -> normalizer -> builder)\n";

$submittedFields = [
    'form-field-name' => '  Kovácsné Szabó Anna ',
    'form-field-email' => 'anna@example.com',
    'form-field-phone' => '06 (30) 123-4567',
    'form-field-dob' => '2005. 03. 14.',
    'form-field-school' => 'ELTE',
    'form-field-message' => 'Hello',
    'form-field-empty' => '',
];
$mappings = [
    ['bricksFieldId' => 'name', 'targetType' => 'standard', 'standardField' => 'name', 'customFieldId' => ''],
    ['bricksFieldId' => 'email', 'targetType' => 'standard', 'standardField' => 'email', 'customFieldId' => ''],
    ['bricksFieldId' => 'phone', 'targetType' => 'standard', 'standardField' => 'phone', 'customFieldId' => ''],
    ['bricksFieldId' => 'dob', 'targetType' => 'standard', 'standardField' => 'dateOfBirth', 'customFieldId' => ''],
    ['bricksFieldId' => 'school', 'targetType' => 'custom', 'standardField' => '', 'customFieldId' => 'cf_school'],
    ['bricksFieldId' => 'message', 'targetType' => 'note', 'standardField' => '', 'customFieldId' => ''],
    ['bricksFieldId' => 'empty', 'targetType' => 'standard', 'standardField' => 'city', 'customFieldId' => ''],
];

$mapped = (new FieldMapper())->map($submittedFields, $mappings);
$normalized = $normalizer->normalize($mapped['standard'], 'HU', $familyFirst);
$mapped['standard'] = $normalized['standard'];
$payload = (new ContactPayloadBuilder())->build('loc_123', 'Website Bricks Form', $mapped);

$check('payload', [
    'locationId' => 'loc_123',
    'source' => 'Website Bricks Form',
    'name' => 'Kovácsné Szabó Anna',
    'email' => 'anna@example.com',
    'phone' => '+36301234567',
    'dateOfBirth' => '2005-03-14',
    'firstName' => 'Anna',
    'lastName' => 'Kovácsné Szabó',
    'customFields' => [['id' => 'cf_school', 'value' => 'ELTE']],
], $payload);
$check('payload has no tags key', false, array_key_exists('tags', $payload));
$check('payload has no note text', false, in_array('Hello', $payload, true));
$check('normalization warnings', [], $normalized['warnings']);

$mapped = (new FieldMapper())->map($submittedFields, $mappings);
$normalized = $normalizer->normalize($mapped['standard'], '', $givenFirst);
$mapped['standard'] = $normalized['standard'];
$payload = (new ContactPayloadBuilder())->build('loc_123', 'Website Bricks Form', $mapped);
$check('payload with default settings keeps phone and name as submitted', [
    'phone' => '06 (30) 123-4567',
    'name' => 'Kovácsné Szabó Anna',
    'firstName' => null,
    'lastName' => null,
], [
    'phone' => $payload['phone'] ?? null,
    'name' => $payload['name'] ?? null,
    'firstName' => $payload['firstName'] ?? null,
    'lastName' => $payload['lastName'] ?? null,
]);

echo "\n";

if ($bghlSmokeFailures > 0) {
    echo sprintf("%d check(s) failed.\n", $bghlSmokeFailures);
    exit(1);
}

echo "All checks passed.\n";
