<?php

declare(strict_types=1);

namespace Opencart\Admin\Model\Extension\Alliance\Payment\Refund;

use Opencart\System\Engine\Registry;
use Opencart\System\Helper\Alliance\Db\RefundHistory;
use Opencart\System\Helper\Alliance\Db\RefundHistoryMapper;

class History extends \Opencart\System\Engine\Model
{
    private $refundhistory;

    public function __construct(Registry $registry)
    {
        parent::__construct($registry);
        // extension/alliance/system/helper/alliance/db/refund.php
        $this->load->helper('extension/alliance/alliance/db/refundhistorymapper');
        $this->load->helper('extension/alliance/alliance/refund/refundhistory');
        $this->refundhistory = new RefundHistory($registry);
    }

    /**
     * @param array $data
     * @param int $orderId
     * @return void
     */
    public function updateRefundHistory(array $data, int $orderId): void
    {
        $this->refundhistory->updateRefundHistory($data, $orderId);
    }

    public function getRefundOrder(int $orderId): ?array
    {
        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "alliance_integration_order_refund` WHERE `order_id` = '" . $orderId . "'");

        return $query->rows;
    }

    public function updateOrderHistoryAndStatus(
        int $orderId,
        int $orderStatusId,
        string $comment = '',
        int $orderNotify = 0
    ): void {
        $this->db->query(
            "UPDATE `" . DB_PREFIX . "order` SET order_status_id = '" . $orderStatusId
            . "', date_modified = NOW() WHERE order_id = '" . (int)$orderId . "'");

        $this->db->query("INSERT INTO `" . DB_PREFIX . "order_history` SET order_id = '" . $orderId
            . "',order_status_id = '" . $orderStatusId . "',notify = '" . $orderNotify
            . "',comment = '" . $this->db->escape($comment)
            . "',date_added = NOW()");
    }

    public function getRefundOperationsTotal(int $orderId): int
    {
        $refundTotal = 0;

        $query = $this->db->query("SELECT coin_amount FROM `"
            . DB_PREFIX . "alliance_integration_order_refund` WHERE `order_id` = '"
            . $orderId . "'"
        );

        foreach ($query->rows as $row) {
            $refundTotal += $row['coin_amount'];
        }

        return $refundTotal;
    }
}
