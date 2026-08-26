<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace Opencart\System\Helper\Alliance;

use League\ISO3166\ISO3166;

class AllianceCountryCodeProvider
{
    private ISO3166 $countryCode;

    public function __construct()
    {
        $composerAutoload = DIR_EXTENSION . 'alliance/system/library/vendor/autoload.php';
        $composerAutoloadByPath = DIR_EXTENSION . 'alliance/vendor/autoload.php';

        if (file_exists($composerAutoload)) {
            require_once $composerAutoload;
        } else if (file_exists($composerAutoloadByPath)) {
            require_once $composerAutoloadByPath;
        } else {
            $rootAutoload = DIR_SYSTEM . '../vendor/autoload.php';
            if (file_exists($rootAutoload)) {
                require_once $rootAutoload;
            } else {
                throw new \Exception('AlliancePay: Autoloader not found.');
            }
        }
        $this->countryCode = new ISO3166();
    }

    /**
     * @param string $alpha2
     * @return string
     */
    public function getCountryNumericCodeByAlpha2(string $alpha2): string
    {
        $countryData = $this->countryCode->alpha2($alpha2);

        return $countryData['numeric'] ?? '';
    }
}
