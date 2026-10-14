<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace Opencart\Admin\Model\Extension\Alliance\Payment;

/**
 * Class Subscription.
 */
class Subscription extends \Opencart\System\Engine\Model
{
    /**
     * @param int $subscriptionId
     * @return array
     */
    public function getSubscription(int $subscriptionId): array
    {
        $query = $this->db->query(
            "SELECT * FROM `" . DB_PREFIX . "alliance_subscription`"
            . " WHERE `subscription_id` = '" . $subscriptionId . "'"
        );

        return $query->row;
    }

    /**
     * @param string $providerOrderId
     * @return array
     */
    public function getSubscriptionByProviderOrderId(string $providerOrderId): array
    {
        $query = $this->db->query(
            "SELECT * FROM `" . DB_PREFIX . "alliance_subscription`"
            . " WHERE `provider_order_id` = '" . $this->db->escape($providerOrderId) . "'"
        );

        return $query->row;
    }

    /**
     * @param int $start
     * @param int $limit
     * @return array
     */
    public function getSubscriptions(int $start = 0, int $limit = 20): array
    {
        $query = $this->db->query(
            "SELECT s.*, p.name AS plan_name FROM `" . DB_PREFIX . "alliance_subscription` s"
            . " LEFT JOIN `" . DB_PREFIX . "alliance_subscription_plan` p ON (s.subscription_plan_id = p.subscription_plan_id)"
            . " ORDER BY s.date_added DESC LIMIT " . (int)$start . ", " . (int)$limit
        );

        return $query->rows;
    }

    /**
     * @return int
     */
    public function getTotalSubscriptions(): int
    {
        $query = $this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "alliance_subscription`");

        return (int)$query->row['total'];
    }

    /**
     * @param int $subscriptionId
     * @return array
     */
    public function getScheduledPayments(int $subscriptionId): array
    {
        $query = $this->db->query(
            "SELECT * FROM `" . DB_PREFIX . "alliance_scheduled_payment`"
            . " WHERE `subscription_id` = '" . $subscriptionId . "'"
            . " ORDER BY `scheduled_date` ASC"
        );

        return $query->rows;
    }

    /**
     * @param string $providerOrderId
     * @return void
     */
    public function cancelSubscriptionLocal(string $providerOrderId): void
    {
        $this->db->query(
            "UPDATE `" . DB_PREFIX . "alliance_subscription` SET"
            . " `status` = 'CANCELED',"
            . " `date_modified` = NOW()"
            . " WHERE `provider_order_id` = '" . $this->db->escape($providerOrderId) . "'"
        );
    }

    /**
     * @param int $scheduledPaymentId
     * @param string $status
     * @param string $operationId
     * @return void
     */
    public function recordManualTriggerResult(int $scheduledPaymentId, string $status, string $operationId = ''): void
    {
        $this->db->query(
            "UPDATE `" . DB_PREFIX . "alliance_scheduled_payment` SET"
            . " `status` = '" . $this->db->escape($status) . "',"
            . " `operation_id` = " . ($operationId !== '' ? "'" . $this->db->escape($operationId) . "'" : '`operation_id`') . ","
            . " `executed_at` = NOW(),"
            . " `retry_count` = `retry_count` + 1,"
            . " `date_modified` = NOW()"
            . " WHERE `scheduled_payment_id` = '" . $scheduledPaymentId . "'"
        );
    }

    /**
     * @param int $scheduledPaymentId
     * @return array
     */
    public function getScheduledPayment(int $scheduledPaymentId): array
    {
        $query = $this->db->query(
            "SELECT * FROM `" . DB_PREFIX . "alliance_scheduled_payment`"
            . " WHERE `scheduled_payment_id` = '" . $scheduledPaymentId . "'"
        );

        return $query->row;
    }
}
