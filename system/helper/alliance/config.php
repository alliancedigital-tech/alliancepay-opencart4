<?php

declare(strict_types=1);

namespace Opencart\System\Helper\Alliance;

/**
 * Class Config.
 */
class Config
{
    public const ALLIANCE_BASE_CONFIG_KEY = 'payment_alliance';
    public const ALLIANCE_PAYMENT_CODE    = 'alliance.alliance';

    public const HPP_PAY_TYPE_PREAUTH = 'PREAUTH';

    public const PREAUTH_EXP_DATE_OPTIONS = [
        '2h'  => '2 hours',
        '4h'  => '4 hours',
        '6h'  => '6 hours',
        '12h' => '12 hours',
        '1d'  => '1 day',
        '2d'  => '2 days',
        '7d'  => '7 days',
        '14d' => '14 days',
        '28d' => '28 days',
    ];

    public const PREAUTH_EXP_DATE_BUFFER_SECONDS = 30;

    public const PREAUTH_AMOUNT_TOLERANCE = 0.20;
}
