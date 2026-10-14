<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace Opencart\Admin\Model\Extension\Alliance\Payment;

/**
 * Class Subscriptionplan.
 */
class Subscriptionplan extends \Opencart\System\Engine\Model
{
    /**
     * @param string $planKey
     * @return array
     */
    public function findByPlanKey(string $planKey): array
    {
        $query = $this->db->query(
            "SELECT * FROM `" . DB_PREFIX . "alliance_subscription_plan`"
            . " WHERE `plan_key` = '" . $this->db->escape($planKey) . "'"
        );

        return $query->row;
    }

    /**
     * @param int $subscriptionPlanId
     * @return array
     */
    public function getSubscriptionPlan(int $subscriptionPlanId): array
    {
        $query = $this->db->query(
            "SELECT * FROM `" . DB_PREFIX . "alliance_subscription_plan`"
            . " WHERE `subscription_plan_id` = '" . $subscriptionPlanId . "'"
        );

        return $query->row;
    }

    /**
     * @param int $start
     * @param int $limit
     * @return array
     */
    public function getSubscriptionPlans(int $start = 0, int $limit = 20): array
    {
        $query = $this->db->query(
            "SELECT * FROM `" . DB_PREFIX . "alliance_subscription_plan`"
            . " ORDER BY `date_added` DESC LIMIT " . (int)$start . ", " . (int)$limit
        );

        return $query->rows;
    }

    /**
     * @return int
     */
    public function getTotalSubscriptionPlans(): int
    {
        $query = $this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "alliance_subscription_plan`");

        return (int)$query->row['total'];
    }

    /**
     * @param array $data
     * @return int
     */
    public function createPlanLocal(array $data): int
    {
        $this->db->query(
            "INSERT INTO `" . DB_PREFIX . "alliance_subscription_plan` SET"
            . " `provider_plan_id` = '" . $this->db->escape($data['provider_plan_id']) . "',"
            . " `product_id` = '" . (int)$data['product_id'] . "',"
            . " `template_id` = " . ($data['template_id'] !== null ? "'" . (int)$data['template_id'] . "'" : 'NULL') . ","
            . " `plan_key` = '" . $this->db->escape($data['plan_key']) . "',"
            . " `version_id` = '" . $this->db->escape($data['version_id']) . "',"
            . " `category_indicator` = '" . $this->db->escape($data['category_indicator']) . "',"
            . " `merchant_id` = '" . $this->db->escape($data['merchant_id']) . "',"
            . " `name` = '" . $this->db->escape($data['name']) . "',"
            . " `description` = '" . $this->db->escape($data['description'] ?? '') . "',"
            . " `rules` = '" . $this->db->escape(json_encode($data['rules'], JSON_UNESCAPED_UNICODE)) . "',"
            . " `status` = 'ACTIVE',"
            . " `is_active` = 1,"
            . " `sync_status` = 'SYNCED',"
            . " `date_added` = NOW(),"
            . " `date_modified` = NOW()"
        );

        return (int)$this->db->getLastId();
    }

    /**
     * @param array $data
     * @param string $syncError
     * @return int
     */
    public function createPlanError(array $data, string $syncError): int
    {
        $this->db->query(
            "INSERT INTO `" . DB_PREFIX . "alliance_subscription_plan` SET"
            . " `provider_plan_id` = '" . $this->db->escape($data['provider_plan_id'] ?? '') . "',"
            . " `product_id` = '" . (int)$data['product_id'] . "',"
            . " `template_id` = " . ($data['template_id'] !== null ? "'" . (int)$data['template_id'] . "'" : 'NULL') . ","
            . " `plan_key` = '" . $this->db->escape($data['plan_key']) . "',"
            . " `version_id` = '" . $this->db->escape($data['version_id'] ?? '') . "',"
            . " `category_indicator` = '" . $this->db->escape($data['category_indicator']) . "',"
            . " `merchant_id` = '" . $this->db->escape($data['merchant_id']) . "',"
            . " `name` = '" . $this->db->escape($data['name']) . "',"
            . " `description` = '" . $this->db->escape($data['description'] ?? '') . "',"
            . " `rules` = '" . $this->db->escape(json_encode($data['rules'], JSON_UNESCAPED_UNICODE)) . "',"
            . " `status` = 'ERROR',"
            . " `is_active` = 0,"
            . " `sync_status` = 'ERROR',"
            . " `sync_error` = '" . $this->db->escape($syncError) . "',"
            . " `date_added` = NOW(),"
            . " `date_modified` = NOW()"
        );

        return (int)$this->db->getLastId();
    }

    /**
     * @param int $subscriptionPlanId
     * @param string $syncError
     * @return void
     */
    public function markPlanError(int $subscriptionPlanId, string $syncError): void
    {
        $this->db->query(
            "UPDATE `" . DB_PREFIX . "alliance_subscription_plan` SET"
            . " `status` = 'ERROR',"
            . " `sync_status` = 'ERROR',"
            . " `sync_error` = '" . $this->db->escape($syncError) . "',"
            . " `date_modified` = NOW()"
            . " WHERE `subscription_plan_id` = '" . $subscriptionPlanId . "'"
        );
    }

    /**
     * @param int $subscriptionPlanId
     * @param array $newRules
     * @param string $newVersionId
     * @param string $appliesFrom
     * @param string $reason
     * @param int $createdBy
     * @return void
     */
    public function reprice(
        int $subscriptionPlanId,
        array $newRules,
        string $newVersionId,
        string $appliesFrom,
        string $reason,
        int $createdBy
    ): void {
        $plan = $this->getSubscriptionPlan($subscriptionPlanId);

        $this->db->query(
            "INSERT INTO `" . DB_PREFIX . "alliance_subscription_plan_version_history` SET"
            . " `subscription_plan_id` = '" . $subscriptionPlanId . "',"
            . " `old_version_id` = '" . $this->db->escape($plan['version_id'] ?? '') . "',"
            . " `new_version_id` = '" . $this->db->escape($newVersionId) . "',"
            . " `old_rules` = '" . $this->db->escape($plan['rules'] ?? '[]') . "',"
            . " `new_rules` = '" . $this->db->escape(json_encode($newRules, JSON_UNESCAPED_UNICODE)) . "',"
            . " `applies_from` = '" . $this->db->escape($appliesFrom) . "',"
            . " `notified_confirmed` = 1,"
            . " `reason` = '" . $this->db->escape($reason) . "',"
            . " `created_by` = '" . $createdBy . "',"
            . " `date_added` = NOW()"
        );

        $this->db->query(
            "UPDATE `" . DB_PREFIX . "alliance_subscription_plan` SET"
            . " `rules` = '" . $this->db->escape(json_encode($newRules, JSON_UNESCAPED_UNICODE)) . "',"
            . " `version_id` = '" . $this->db->escape($newVersionId) . "',"
            . " `date_modified` = NOW()"
            . " WHERE `subscription_plan_id` = '" . $subscriptionPlanId . "'"
        );
    }

    /**
     * @param int $subscriptionPlanId
     * @return void
     */
    public function deactivateLocalOnly(int $subscriptionPlanId): void
    {
        $this->db->query(
            "UPDATE `" . DB_PREFIX . "alliance_subscription_plan` SET"
            . " `is_active` = 0,"
            . " `date_modified` = NOW()"
            . " WHERE `subscription_plan_id` = '" . $subscriptionPlanId . "'"
        );
    }

    /**
     * @return array
     */
    public function getArchiveCandidates(): array
    {
        $query = $this->db->query(
            "SELECT * FROM `" . DB_PREFIX . "alliance_subscription_plan`"
            . " WHERE `is_active` = 0 AND `active_subscriptions_count` = 0 AND `status` != 'ARCHIVED'"
        );

        return $query->rows;
    }

    /**
     * @param int $subscriptionPlanId
     * @return array
     */
    public function getVersionHistory(int $subscriptionPlanId): array
    {
        $query = $this->db->query(
            "SELECT * FROM `" . DB_PREFIX . "alliance_subscription_plan_version_history`"
            . " WHERE `subscription_plan_id` = '" . $subscriptionPlanId . "'"
            . " ORDER BY `date_added` DESC"
        );

        return $query->rows;
    }

    /**
     * @param int $subscriptionPlanId
     * @return void
     */
    public function incrementActiveSubscriptions(int $subscriptionPlanId): void
    {
        $this->db->query(
            "UPDATE `" . DB_PREFIX . "alliance_subscription_plan` SET"
            . " `active_subscriptions_count` = `active_subscriptions_count` + 1"
            . " WHERE `subscription_plan_id` = '" . $subscriptionPlanId . "'"
        );
    }

    /**
     * @param int $subscriptionPlanId
     * @return void
     */
    public function decrementActiveSubscriptions(int $subscriptionPlanId): void
    {
        $this->db->query(
            "UPDATE `" . DB_PREFIX . "alliance_subscription_plan` SET"
            . " `active_subscriptions_count` = GREATEST(`active_subscriptions_count` - 1, 0)"
            . " WHERE `subscription_plan_id` = '" . $subscriptionPlanId . "'"
        );
    }

    /**
     * @param int|null $subscriptionPlanId
     * @param int|null $productId
     * @param int $userId
     * @param string $action
     * @param array $details
     * @return void
     */
    public function addAuditLog(?int $subscriptionPlanId, ?int $productId, int $userId, string $action, array $details = []): void
    {
        $this->db->query(
            "INSERT INTO `" . DB_PREFIX . "alliance_subscription_audit_log` SET"
            . " `action` = '" . $this->db->escape($action) . "',"
            . " `subscription_plan_id` = " . ($subscriptionPlanId !== null ? "'" . $subscriptionPlanId . "'" : 'NULL') . ","
            . " `product_id` = " . ($productId !== null ? "'" . $productId . "'" : 'NULL') . ","
            . " `user_id` = '" . $userId . "',"
            . " `details` = '" . $this->db->escape(json_encode($details, JSON_UNESCAPED_UNICODE)) . "',"
            . " `date_added` = NOW()"
        );
    }
}
