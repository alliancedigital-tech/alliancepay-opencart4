<?php

declare(strict_types=1);

namespace Opencart\System\Config\Alliance;

class Config extends \Opencart\System\Engine\Config
{
    public const ALLIANCE_BASE_CONFIG_KEY = 'payment_alliance';

    public const ALLIANCE_PAYMENT_METHOD_CODE_KEY = '_payment_method_code';

    public const ALLIANCE_PAYMENT_CODE = 'alliance.alliance';

    public function __construct() {
        parent::__construct();
        $this->set(
            self::ALLIANCE_BASE_CONFIG_KEY . self::ALLIANCE_PAYMENT_METHOD_CODE_KEY,
            self::ALLIANCE_PAYMENT_CODE
        );
    }
}
