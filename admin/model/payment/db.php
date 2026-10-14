<?php

namespace Opencart\Admin\Model\Extension\Alliance\Payment;

class Db extends \Opencart\System\Engine\Model
{
	public function initDB(): void
	{
		$this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "alliance_checkout_integration_order_callback` (
			`order_id` INT(11) NOT NULL,
			`ecom_order_id` VARCHAR(255) NOT NULL,
			`coin_amount` INT(11) NOT NULL,
			`merchant_id` VARCHAR(255) NOT NULL,
			`status_url` VARCHAR(255) NOT NULL,
			`hpp_order_id` VARCHAR(255) NOT NULL,
			`redirect_url` VARCHAR(255) NOT NULL,
			`notification_url` VARCHAR(255) NOT NULL,
			`hpp_pay_type` VARCHAR(50) NOT NULL,
			`merchant_request_id` VARCHAR(255) NOT NULL,
			`order_status` VARCHAR(50) NOT NULL,
			`operation_id` VARCHAR(255) NOT NULL,
			`create_date` DATETIME NOT NULL,
			`payment_methods` TEXT NOT NULL,
			`expired_order_date` DATETIME NOT NULL,
			`operations` TEXT NOT NULL,
			PRIMARY KEY (`order_id`),
			KEY `ecom_order_id` (`ecom_order_id`),
			KEY `merchant_id` (`merchant_id`),
			KEY `hpp_order_id` (`hpp_order_id`),
			KEY `operation_id` (`operation_id`),
			KEY `order_id` (`order_id`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "alliance_checkout_integration_order`
		(
			`order_id` INT(11) NOT NULL,
			`merchant_request_id` VARCHAR(255) NOT NULL,
			`hpp_order_id` VARCHAR(255) NOT NULL,
			`merchant_id` VARCHAR(255) NOT NULL,
			`coin_amount` INT(11) NOT NULL,
			`hpp_pay_type` VARCHAR(50) NOT NULL,
			`order_status` VARCHAR(50) NOT NULL,
			`payment_methods` TEXT NOT NULL,
			`create_date` DATETIME NOT NULL,
			`updated_at` DATETIME,
            `operation_id` VARCHAR(255),
            `ecom_order_id` VARCHAR(255),
            `is_callback_returned` BOOLEAN DEFAULT FALSE,
            `callback_data` LONGTEXT,
            `original_authorized_amount` INT(11) DEFAULT NULL,
            `currency_code` INT(3) DEFAULT NULL,
            `conversion_rate` DECIMAL(20,6) DEFAULT NULL,
			`expired_order_date` DATETIME NOT NULL,
			PRIMARY KEY (`order_id`),
			KEY `merchant_request_id` (`merchant_request_id`),
			KEY `hpp_order_id` (`hpp_order_id`),
			KEY `merchant_id` (`merchant_id`),
			KEY `order_id` (`order_id`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");


        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "alliance_integration_order_refund`
        (
            `refund_id` int(11) NOT NULL AUTO_INCREMENT,
            `order_id` INT(11) NOT NULL,
            `type` VARCHAR(255) NOT NULL,
            `rrn` VARCHAR(255) NOT NULL,
            `purpose` VARCHAR(255) NOT NULL,
            `comment` VARCHAR(255) NOT NULL,
            `coin_amount` INT(11) NOT NULL,
            `merchant_id` VARCHAR(255) NOT NULL,
            `operation_id` VARCHAR(255) NOT NULL,
            `ecom_operation_id` VARCHAR(255) NOT NULL,
            `merchant_name` VARCHAR(255) NULL,
            `approval_code` VARCHAR(255) NOT NULL,
            `status` VARCHAR(255) NOT NULL,
            `transaction_type` INT(11) NOT NULL,
            `merchant_request_id` VARCHAR(255) NOT NULL,
            `transaction_currency` VARCHAR(255) NOT NULL,
            `merchant_commission` INT(11) NULL,
            `create_date_time` DATETIME NOT NULL,
            `modification_date_time` DATETIME NOT NULL,
            `action_code` VARCHAR(255) NOT NULL,
            `response_code` VARCHAR(255) NOT NULL,
            `description` VARCHAR(255) NOT NULL,
            `processing_merchant_id` VARCHAR(255) NOT NULL,
            `processing_terminal_id` VARCHAR(255) NOT NULL,
            `transaction_response_info` TEXT NOT NULL,
            `bank_code` VARCHAR(255) NOT NULL,
            `payment_system` VARCHAR(255) NOT NULL,
            `product_type` VARCHAR(255) NOT NULL,
            `notification_url` VARCHAR(255) NOT NULL,
            `payment_service_type` VARCHAR(255) NOT NULL,
            `notification_encryption` VARCHAR(255) NOT NULL,
            `original_operation_id` VARCHAR(255) NOT NULL,
            `original_coin_amount` INT(11) NOT NULL,
            `original_ecom_operation_id` VARCHAR(255) NOT NULL,
            `rrn_original` VARCHAR(255) NOT NULL,
            PRIMARY KEY (`refund_id`),
            KEY `refund_id` (`refund_id`),
            KEY `merchant_request_id` (`merchant_request_id`),
            KEY `merchant_id` (`merchant_id`),
            KEY `order_id` (`order_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

        $this->initSubscriptionTables();
    }

    public function initSubscriptionTables(): void
    {
        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "alliance_subscription_plan_template`
        (
            `template_id` INT(11) NOT NULL AUTO_INCREMENT,
            `code` VARCHAR(255) NOT NULL,
            `name` VARCHAR(255) NOT NULL,
            `category_indicator` VARCHAR(255) NOT NULL,
            `rules_template` LONGTEXT NOT NULL,
            `status` VARCHAR(20) NOT NULL DEFAULT 'active',
            `date_added` DATETIME NOT NULL,
            `date_modified` DATETIME NOT NULL,
            PRIMARY KEY (`template_id`),
            UNIQUE KEY `code` (`code`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "alliance_subscription_plan`
        (
            `subscription_plan_id` INT(11) NOT NULL AUTO_INCREMENT,
            `provider_plan_id` VARCHAR(255) DEFAULT NULL,
            `product_id` INT(11) NOT NULL,
            `template_id` INT(11) DEFAULT NULL,
            `plan_key` VARCHAR(64) NOT NULL,
            `version_id` VARCHAR(255) DEFAULT NULL,
            `category_indicator` VARCHAR(255) NOT NULL,
            `merchant_id` VARCHAR(255) NOT NULL,
            `name` VARCHAR(255) NOT NULL,
            `description` VARCHAR(255) DEFAULT NULL,
            `rules` LONGTEXT NOT NULL,
            `status` VARCHAR(20) NOT NULL,
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `active_subscriptions_count` INT(11) NOT NULL DEFAULT 0,
            `sync_status` VARCHAR(20) NOT NULL,
            `sync_error` TEXT,
            `applies_from` DATETIME DEFAULT NULL,
            `applies_to` DATETIME DEFAULT NULL,
            `date_added` DATETIME NOT NULL,
            `date_modified` DATETIME NOT NULL,
            PRIMARY KEY (`subscription_plan_id`),
            UNIQUE KEY `plan_key` (`plan_key`),
            KEY `provider_plan_id` (`provider_plan_id`),
            KEY `product_id` (`product_id`),
            KEY `is_active` (`is_active`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "alliance_subscription`
        (
            `subscription_id` INT(11) NOT NULL AUTO_INCREMENT,
            `order_id` INT(11) NOT NULL,
            `subscription_plan_id` INT(11) NOT NULL,
            `customer_id` INT(11) DEFAULT NULL,
            `rules_snapshot` LONGTEXT NOT NULL,
            `provider_order_id` VARCHAR(255) DEFAULT NULL,
            `merchant_request_id` VARCHAR(255) DEFAULT NULL,
            `status` VARCHAR(20) NOT NULL,
            `callback_data` LONGTEXT,
            `date_added` DATETIME NOT NULL,
            `date_modified` DATETIME NOT NULL,
            PRIMARY KEY (`subscription_id`),
            KEY `order_id` (`order_id`),
            KEY `subscription_plan_id` (`subscription_plan_id`),
            KEY `provider_order_id` (`provider_order_id`),
            KEY `customer_id` (`customer_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "alliance_scheduled_payment`
        (
            `scheduled_payment_id` INT(11) NOT NULL AUTO_INCREMENT,
            `subscription_id` INT(11) NOT NULL,
            `provider_order_id` VARCHAR(255) DEFAULT NULL,
            `provider_scheduled_payment_id` VARCHAR(255) DEFAULT NULL,
            `status` VARCHAR(20) NOT NULL,
            `scheduled_date` DATETIME DEFAULT NULL,
            `executed_at` DATETIME DEFAULT NULL,
            `operation_id` VARCHAR(255) DEFAULT NULL,
            `retry_count` INT(11) NOT NULL DEFAULT 0,
            `date_added` DATETIME NOT NULL,
            `date_modified` DATETIME NOT NULL,
            PRIMARY KEY (`scheduled_payment_id`),
            KEY `subscription_id` (`subscription_id`),
            KEY `provider_order_id` (`provider_order_id`),
            KEY `operation_id` (`operation_id`),
            KEY `provider_scheduled_payment_id` (`provider_scheduled_payment_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "alliance_subscription_plan_version_history`
        (
            `version_history_id` INT(11) NOT NULL AUTO_INCREMENT,
            `subscription_plan_id` INT(11) NOT NULL,
            `old_version_id` VARCHAR(255) DEFAULT NULL,
            `new_version_id` VARCHAR(255) DEFAULT NULL,
            `old_rules` LONGTEXT,
            `new_rules` LONGTEXT NOT NULL,
            `applies_from` DATETIME NOT NULL,
            `notified_confirmed` TINYINT(1) NOT NULL DEFAULT 0,
            `reason` VARCHAR(255) DEFAULT NULL,
            `created_by` INT(11) DEFAULT NULL,
            `date_added` DATETIME NOT NULL,
            PRIMARY KEY (`version_history_id`),
            KEY `subscription_plan_id` (`subscription_plan_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "alliance_subscription_audit_log`
        (
            `audit_log_id` INT(11) NOT NULL AUTO_INCREMENT,
            `action` VARCHAR(100) NOT NULL,
            `subscription_plan_id` INT(11) DEFAULT NULL,
            `product_id` INT(11) DEFAULT NULL,
            `user_id` INT(11) DEFAULT NULL,
            `details` LONGTEXT,
            `date_added` DATETIME NOT NULL,
            PRIMARY KEY (`audit_log_id`),
            KEY `subscription_plan_id` (`subscription_plan_id`),
            KEY `action` (`action`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");
    }

    public function updateAllianceOrderTable()
    {
        $columnsToAdd = "'updated_at', 'ecom_order_id', 'is_callback_returned', 'callback_data', 'original_authorized_amount', 'currency_code', 'conversion_rate'";

        $columnsDefinition = [
            'updated_at'                 => ' DATETIME NOT NULL AFTER `create_date`',
            'operation_id'               => ' VARCHAR(255) AFTER `payment_methods`',
            'ecom_order_id'              => ' VARCHAR(255) AFTER `payment_methods`',
            'is_callback_returned'       => ' BOOLEAN DEFAULT FALSE AFTER `payment_methods`',
            'callback_data'              => ' LONGTEXT AFTER `payment_methods`',
            'original_authorized_amount' => ' INT(11) DEFAULT NULL AFTER `callback_data`',
            'currency_code'              => ' INT(3) DEFAULT NULL AFTER `original_authorized_amount`',
            'conversion_rate'            => ' DECIMAL(20,6) DEFAULT NULL AFTER `currency_code`',
        ];

        $orderIdIsPrimary = $this->db->query(
            "SELECT COLUMN_KEY FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '"
            . DB_DATABASE
            . "' AND TABLE_NAME = '"
            . DB_PREFIX
            . "alliance_checkout_integration_order' AND COLUMN_NAME='order_id'");

        if ($orderIdIsPrimary == 'PRI') {
            $this->db->query(
                "ALTER TABLE `" . DB_PREFIX
                . "alliance_integration_order_refund` DROP PRIMARY KEY");
            $this->db->query(
                "ALTER TABLE `" . DB_PREFIX
                . "alliance_integration_order_refund` ADD `refund_id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY");
        }

        $existingFields = $this->db->query(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '"
            . DB_DATABASE
            . "' AND TABLE_NAME = '"
            . DB_PREFIX
            . "alliance_checkout_integration_order'"
        );

        $existingColumns = array_column($existingFields->rows, 'COLUMN_NAME');

        foreach ($columnsDefinition as $column => $definition) {
            if (!in_array($column, $existingColumns)) {
                $this->db->query(
                    "ALTER TABLE `" . DB_PREFIX
                    . "alliance_checkout_integration_order` ADD `" . $column . "`" . $definition
                );
            }
        }
    }
}
