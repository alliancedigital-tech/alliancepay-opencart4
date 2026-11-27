<?php

declare(strict_types=1);

$root = dirname(__DIR__);

if (is_file($root . '/vendor/autoload.php')) {
    require_once $root . '/vendor/autoload.php';
}

require_once $root . '/system/helper/alliance/config.php';
require_once $root . '/system/helper/alliance/fiscalization/fiscalizationvalidator.php';
require_once $root . '/system/helper/alliance/fiscalization/fiscalizationvalueresolver.php';
require_once $root . '/system/helper/alliance/fiscalization/fiscalizationproductsbuilder.php';
require_once $root . '/system/helper/alliance/fiscalization/fiscalizationrepository.php';
require_once $root . '/system/helper/alliance/fiscalization/fiscalizationservice.php';
