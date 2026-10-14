<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace Opencart\Catalog\Model\Extension\Alliance\Payment;

/**
 * Class Subscription.
 */
class Subscription extends \Opencart\System\Engine\Model
{
    /**
     * @param int $productId
     * @return array
     */
    public function getActivePlansForProduct(int $productId): array
    {
        $query = $this->db->query(
            "SELECT * FROM `" . DB_PREFIX . "alliance_subscription_plan`"
            . " WHERE `product_id` = '" . $productId . "' AND `is_active` = 1 AND `status` = 'ACTIVE'"
        );

        return $query->rows;
    }

    /**
     * @param object $session
     * @param int $productId
     * @return array
     */
    public function getSelectedSubscriptionPlan(object $session, int $productId): array
    {
        $selectedPlanId = (int)($session->data['alliance_selected_subscription_plan_id'] ?? 0);

        if (!$selectedPlanId) {
            return [];
        }

        $query = $this->db->query(
            "SELECT * FROM `" . DB_PREFIX . "alliance_subscription_plan`"
            . " WHERE `subscription_plan_id` = '" . $selectedPlanId . "'"
            . " AND `product_id` = '" . $productId . "' AND `is_active` = 1 AND `status` = 'ACTIVE'"
        );

        return $query->row;
    }

    /**
     * @param array $productIds
     * @param int|null $selectedProductId
     * @param bool $isLoggedIn
     * @return string
     */
    public function checkCartSubscriptionGuard(array $productIds, ?int $selectedProductId, bool $isLoggedIn): string
    {
        if ($selectedProductId === null) {
            return '';
        }

        if (!$isLoggedIn) {
            return 'guest';
        }

        $uniqueProductIds = array_unique(array_map('intval', $productIds));

        if (count($uniqueProductIds) > 1 || !in_array($selectedProductId, $uniqueProductIds, true)) {
            return 'mixed';
        }

        return '';
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
     * @param int $orderId
     * @param int $subscriptionPlanId
     * @param int|null $customerId
     * @param array $rulesSnapshot
     * @return int
     */
    public function createSubscription(int $orderId, int $subscriptionPlanId, ?int $customerId, array $rulesSnapshot): int
    {
        $this->db->query(
            "INSERT INTO `" . DB_PREFIX . "alliance_subscription` SET"
            . " `order_id` = '" . $orderId . "',"
            . " `subscription_plan_id` = '" . $subscriptionPlanId . "',"
            . " `customer_id` = " . ($customerId !== null ? "'" . $customerId . "'" : 'NULL') . ","
            . " `rules_snapshot` = '" . $this->db->escape(json_encode($rulesSnapshot, JSON_UNESCAPED_UNICODE)) . "',"
            . " `status` = 'PENDING',"
            . " `date_added` = NOW(),"
            . " `date_modified` = NOW()"
        );

        return (int)$this->db->getLastId();
    }

    /**
     * @param int $subscriptionId
     * @param string $providerOrderId
     * @param string $merchantRequestId
     * @return void
     */
    public function saveSubscriptionOrderCreated(int $subscriptionId, string $providerOrderId, string $merchantRequestId): void
    {
        $this->db->query(
            "UPDATE `" . DB_PREFIX . "alliance_subscription` SET"
            . " `provider_order_id` = '" . $this->db->escape($providerOrderId) . "',"
            . " `merchant_request_id` = '" . $this->db->escape($merchantRequestId) . "',"
            . " `date_modified` = NOW()"
            . " WHERE `subscription_id` = '" . $subscriptionId . "'"
        );
    }

    /**
     * @param int $subscriptionId
     * @param string $status
     * @return void
     */
    public function updateSubscriptionStatus(int $subscriptionId, string $status): void
    {
        $this->db->query(
            "UPDATE `" . DB_PREFIX . "alliance_subscription` SET"
            . " `status` = '" . $this->db->escape($status) . "',"
            . " `date_modified` = NOW()"
            . " WHERE `subscription_id` = '" . $subscriptionId . "'"
        );
    }

    /**
     * @param int $subscriptionId
     * @param array $rawCallbackData
     * @return void
     */
    public function saveLatestCallbackData(int $subscriptionId, array $rawCallbackData): void
    {
        $this->db->query(
            "UPDATE `" . DB_PREFIX . "alliance_subscription` SET"
            . " `callback_data` = '" . $this->db->escape(json_encode($rawCallbackData, JSON_UNESCAPED_UNICODE)) . "',"
            . " `date_modified` = NOW()"
            . " WHERE `subscription_id` = '" . $subscriptionId . "'"
        );
    }

    /**
     * @param int $subscriptionId
     * @param string $providerScheduledPaymentId
     * @param string $operationId
     * @return array
     */
    public function findScheduledPayment(int $subscriptionId, string $providerScheduledPaymentId, string $operationId): array
    {
        if ($providerScheduledPaymentId !== '') {
            $query = $this->db->query(
                "SELECT * FROM `" . DB_PREFIX . "alliance_scheduled_payment`"
                . " WHERE `subscription_id` = '" . $subscriptionId . "'"
                . " AND `provider_scheduled_payment_id` = '" . $this->db->escape($providerScheduledPaymentId) . "'"
            );

            if ($query->row) {
                return $query->row;
            }
        }

        if ($operationId !== '') {
            $query = $this->db->query(
                "SELECT * FROM `" . DB_PREFIX . "alliance_scheduled_payment`"
                . " WHERE `subscription_id` = '" . $subscriptionId . "'"
                . " AND `operation_id` = '" . $this->db->escape($operationId) . "'"
            );

            if ($query->row) {
                return $query->row;
            }
        }

        return [];
    }

    /**
     * @param int $subscriptionId
     * @param array $data
     * @return int
     */
    public function createScheduledPayment(int $subscriptionId, array $data): int
    {
        $this->db->query(
            "INSERT INTO `" . DB_PREFIX . "alliance_scheduled_payment` SET"
            . " `subscription_id` = '" . $subscriptionId . "',"
            . " `provider_order_id` = '" . $this->db->escape($data['provider_order_id'] ?? '') . "',"
            . " `provider_scheduled_payment_id` = '" . $this->db->escape($data['provider_scheduled_payment_id'] ?? '') . "',"
            . " `status` = '" . $this->db->escape($data['status'] ?? 'WAITING') . "',"
            . " `scheduled_date` = " . (!empty($data['scheduled_date']) ? "'" . $this->db->escape($data['scheduled_date']) . "'" : 'NULL') . ","
            . " `operation_id` = " . (!empty($data['operation_id']) ? "'" . $this->db->escape($data['operation_id']) . "'" : 'NULL') . ","
            . " `date_added` = NOW(),"
            . " `date_modified` = NOW()"
        );

        return (int)$this->db->getLastId();
    }

    /**
     * @param int $scheduledPaymentId
     * @param array $data
     * @return void
     */
    public function updateScheduledPayment(int $scheduledPaymentId, array $data): void
    {
        $set = [];

        if (!empty($data['status'])) {
            $set[] = "`status` = '" . $this->db->escape($data['status']) . "'";
        }

        if (!empty($data['provider_order_id'])) {
            $set[] = "`provider_order_id` = '" . $this->db->escape($data['provider_order_id']) . "'";
        }

        if (!empty($data['provider_scheduled_payment_id'])) {
            $set[] = "`provider_scheduled_payment_id` = '" . $this->db->escape($data['provider_scheduled_payment_id']) . "'";
        }

        if (!empty($data['operation_id'])) {
            $set[] = "`operation_id` = '" . $this->db->escape($data['operation_id']) . "'";
        }

        if (!empty($data['scheduled_date'])) {
            $set[] = "`scheduled_date` = '" . $this->db->escape($data['scheduled_date']) . "'";
        }

        if (!empty($data['executed_at'])) {
            $set[] = "`executed_at` = '" . $this->db->escape($data['executed_at']) . "'";
        }

        if (!$set) {
            return;
        }

        $set[] = "`date_modified` = NOW()";

        $this->db->query(
            "UPDATE `" . DB_PREFIX . "alliance_scheduled_payment` SET " . implode(', ', $set)
            . " WHERE `scheduled_payment_id` = '" . $scheduledPaymentId . "'"
        );
    }
}
