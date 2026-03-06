<?php

declare(strict_types=1);

namespace Opencart\Catalog\Controller\Extension\Alliance\Payment\Event;

/**
 * Class FixSessionOrderId.
 */
class FixSessionOrderId extends \Opencart\System\Engine\Controller
{
    /**
     * @param string $route
     * @param array $args
     * @param mixed $output
     * @return void
     */
    public function afterAddOrder(string &$route, array &$args, mixed &$output): void
    {
        if (!empty($output) && is_numeric($output) && empty($this->session->data['order_id'])) {
            $this->session->data['order_id'] = (int)$output;
        }
    }
}
