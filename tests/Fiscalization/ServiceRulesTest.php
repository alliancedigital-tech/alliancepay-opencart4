<?php

declare(strict_types=1);

use Opencart\System\Helper\Alliance\Fiscalization\FiscalizationService;
use PHPUnit\Framework\TestCase;

class ServiceRulesTest extends TestCase
{
    public function testManualButtonRules(): void
    {
        $this->assertTrue(FiscalizationService::canFiscalize(null));
        $this->assertTrue(FiscalizationService::canFiscalize(['status' => 'ERROR', 'error_code' => 'HTTP_500']));
        $this->assertTrue(FiscalizationService::canFiscalize(['status' => 'ERROR', 'error_code' => 'validation']));
        $this->assertFalse(FiscalizationService::canFiscalize(['status' => 'ERROR', 'error_code' => 'payment_not_found']));
        $this->assertFalse(FiscalizationService::canFiscalize(['status' => 'PENDING', 'error_code' => null]));
        $this->assertFalse(FiscalizationService::canFiscalize(['status' => 'SUCCESS', 'error_code' => null]));
        $this->assertFalse(FiscalizationService::canFiscalize(['status' => 'FAIL', 'error_code' => '541']));
    }
}
