<?php

declare(strict_types=1);

namespace Opencart\System\Helper\Alliance\log;

use Opencart\System\Engine\Registry;
use Opencart\System\Library\Log;

/**
 * Class AllianceLogger.
 */
class AllianceLogger
{
    private const ALLIANCE_LOG_FILE = 'alliance.log';
    private $logger;
    public function __construct(Registry $registry)
    {
        $logger = $registry->get('log');
        $this->logger = new Log(self::ALLIANCE_LOG_FILE);
    }

    public function log($message, string $level = 'INFO'): void {
        if (is_array($message) || is_object($message)) {
            $message = json_encode($message, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }
        $timestamp = date('Y-m-d H:i:s');
        $this->logger->write("[{$timestamp}] [{$level}] {$message}");
    }
}
