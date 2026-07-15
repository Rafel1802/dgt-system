<?php

namespace App\Support;

/**
 * Normalizes phone numbers to US display format wherever they're stored,
 * so every page shows the same style regardless of how staff typed it or
 * how an import file formatted it — and so phone-based customer matching
 * (CrmCustomerMatchService) compares apples to apples instead of missing
 * matches because "+1 207-213-9077" and "(207) 213-9077" are the same
 * number written two different ways.
 */
class PhoneNumberFormatter
{
    public static function format(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $trimmed = trim($raw);
        if ($trimmed === '') {
            return $trimmed;
        }

        $digits = preg_replace('/\D+/', '', $trimmed);

        // Strip a leading US country code so "+1 207-213-9077",
        // "1-207-213-9077", and "2072139077" all normalize the same way.
        if (strlen($digits) === 11 && str_starts_with($digits, '1')) {
            $digits = substr($digits, 1);
        }

        // Only a clean 10-digit US number can be confidently reformatted —
        // extensions, international numbers, or garbled entries are left
        // exactly as typed rather than risk mangling them into something wrong.
        if (strlen($digits) !== 10) {
            return $trimmed;
        }

        return sprintf('+1 (%s) %s-%s', substr($digits, 0, 3), substr($digits, 3, 3), substr($digits, 6));
    }
}
