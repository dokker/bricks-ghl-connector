<?php

declare(strict_types=1);

namespace BricksGhlConnector\Support;

use DateTimeImmutable;

/**
 * Turns a submitted date into the Y-m-d form the GHL DATE fields accept.
 *
 * The Bricks date field already sends Y-m-d, the other formats cover dates
 * typed into a text field, including the Hungarian "2005. 03. 14." style.
 */
final class DateNormalizer
{
    private const FORMATS = [
        'Y-m-d',
        'Y.m.d',
        'Y.m.d.',
        'Y. m. d.',
        'Y. m. d',
        'Y/m/d',
        'd.m.Y',
        'd.m.Y.',
        'd/m/Y',
    ];

    /** Returns the date as Y-m-d, or null when no format parses it cleanly. */
    public function normalize(string $raw): ?string
    {
        $value = preg_replace('/\s+/u', ' ', trim($raw));

        if (! is_string($value) || $value === '') {
            return null;
        }

        foreach (self::FORMATS as $format) {
            $date = DateTimeImmutable::createFromFormat('!' . $format, $value);
            $errors = DateTimeImmutable::getLastErrors();

            if ($date === false) {
                continue;
            }

            // PHP 8.2+ returns false when there were no warnings or errors.
            if (is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) {
                continue;
            }

            // "Y" also accepts fewer than four digits, so "14.03.05" would become year 14.
            if ((int) $date->format('Y') < 1000) {
                continue;
            }

            return $date->format('Y-m-d');
        }

        return null;
    }
}
