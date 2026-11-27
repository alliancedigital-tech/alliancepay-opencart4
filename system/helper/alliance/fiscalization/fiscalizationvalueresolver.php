<?php

declare(strict_types=1);

namespace Opencart\System\Helper\Alliance\Fiscalization;

require_once __DIR__ . '/fiscalizationvalidator.php';

use Opencart\System\Helper\Alliance\Config;

class FiscalizationValueResolver
{
    public static function resolve(array $productValues, array $defaults): array
    {
        $taxCode = self::firstFilled($productValues['tax_code'] ?? null, $defaults['tax_code'] ?? null);
        $classification = self::firstFilled(
            $productValues['classification_code'] ?? null,
            $defaults['classification_code'] ?? null
        );
        $measurement = self::firstFilled(
            $productValues['measurement_code'] ?? null,
            $defaults['measurement_code'] ?? null
        );

        $productLabels = FiscalizationValidator::parseExciseLabels($productValues['excise_labels'] ?? '');
        $labels = $productLabels ?: FiscalizationValidator::parseExciseLabels($defaults['excise_labels'] ?? '');

        $resolved = [];
        if ($measurement !== null) {
            $resolved['measurementCode'] = $measurement;
        }
        if ($taxCode !== null && preg_match('/^\d+$/', $taxCode)) {
            $resolved['taxCode'] = (int)$taxCode;
        }
        if ($classification !== null) {
            $resolved['classificationCode'] = $classification;
        }

        if ($labels && isset($resolved['taxCode'])
            && FiscalizationValidator::isTaxCodeRequiringClassification($resolved['taxCode'])
        ) {
            $resolved['exciseLabels'] = $labels;
        }

        return $resolved;
    }

    public static function validate(array $resolved): array
    {
        $errors = [];

        if (empty($resolved['measurementCode'])) {
            $errors[] = 'measurement_required';
        }
        if (isset($resolved['taxCode'])
            && FiscalizationValidator::isTaxCodeRequiringClassification($resolved['taxCode'])
            && empty($resolved['classificationCode'])
        ) {
            $errors[] = 'classification_required';
        }

        return $errors;
    }

    public static function defaultsFromConfig(object $config): array
    {
        $prefix = 'payment_alliance_fiscalization_';

        return [
            'tax_code' => $config->get($prefix . 'tax_code'),
            'classification_code' => $config->get($prefix . 'classification_code'),
            'excise_labels' => $config->get($prefix . 'excise_labels'),
            'measurement_code' => $config->get($prefix . 'measurement_code')
                ?: Config::FISCALIZATION_MEASUREMENT_CODE_DEFAULT,
        ];
    }

    private static function firstFilled(...$values): ?string
    {
        foreach ($values as $value) {
            if ($value === null) {
                continue;
            }
            $value = trim((string)$value);
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }
}
