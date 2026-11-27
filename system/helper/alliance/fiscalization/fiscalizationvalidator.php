<?php

declare(strict_types=1);

namespace Opencart\System\Helper\Alliance\Fiscalization;

require_once __DIR__ . '/../config.php';

use Opencart\System\Helper\Alliance\Config;

class FiscalizationValidator
{
    public static function isTaxCodeRequiringClassification($taxCode): bool
    {
        return $taxCode !== null
            && $taxCode !== ''
            && in_array((int)$taxCode, Config::FISCALIZATION_TAX_CODES_REQUIRING_CLASSIFICATION, true);
    }

    public static function parseExciseLabels($value): array
    {
        if (is_array($value)) {
            $parts = $value;
        } else {
            $parts = preg_split('/[,\r\n]+/', (string)$value) ?: [];
        }

        $labels = [];
        foreach ($parts as $part) {
            $part = trim((string)$part);
            if ($part !== '') {
                $labels[$part] = $part;
            }
        }

        return array_values($labels);
    }

    public static function formatExciseLabels(array $labels): string
    {
        return implode(', ', $labels);
    }

    public static function validate(
        array $values,
        bool $requireMeasurement = false,
        bool $checkClassification = false
    ): array {
        $errors = [];

        $taxCode = trim((string)($values['tax_code'] ?? ''));
        if ($taxCode !== '' && !preg_match('/^\d+$/', $taxCode)) {
            $errors['tax_code'] = 'error_fiscalization_tax_code';
        }

        $classification = trim((string)($values['classification_code'] ?? ''));
        if ($classification !== '' && !preg_match('/^\d+$/', $classification)) {
            $errors['classification_code'] = 'error_fiscalization_classification_code';
        } elseif ($checkClassification
            && $classification === ''
            && !isset($errors['tax_code'])
            && self::isTaxCodeRequiringClassification($taxCode)
        ) {
            $errors['classification_code'] = 'error_fiscalization_classification_required';
        }

        $measurement = trim((string)($values['measurement_code'] ?? ''));
        if ($requireMeasurement && $measurement === '') {
            $errors['measurement_code'] = 'error_fiscalization_measurement_code';
        }

        return $errors;
    }
}
