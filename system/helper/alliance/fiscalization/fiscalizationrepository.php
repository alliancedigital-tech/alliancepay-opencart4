<?php

declare(strict_types=1);

namespace Opencart\System\Helper\Alliance\Fiscalization;

use Opencart\System\Engine\Registry;

class FiscalizationRepository
{
    public const STATUS_PENDING = 'PENDING';
    public const STATUS_SUCCESS = 'SUCCESS';
    public const STATUS_FAIL = 'FAIL';
    public const STATUS_ERROR = 'ERROR';

    private const COLUMNS = [
        'order_id', 'store_id', 'operation_id', 'operation_type', 'merchant_request_id', 'bucket_id', 'status',
        'receipt_comment', 'fiscal_number', 'receipt_url', 'fiscalization_date_time',
        'fiscalization_modification_date_time', 'error_code', 'error_description', 'request_payload',
        'response_payload', 'callback_payload',
    ];

    private object $db;

    public function __construct(Registry $registry)
    {
        $this->db = $registry->get('db');
    }

    public function findByOperationId(string $operationId): ?array
    {
        return $this->findOne("`operation_id` = '" . $this->db->escape($operationId) . "'");
    }

    public function findByMerchantRequestId(string $merchantRequestId): ?array
    {
        return $this->findOne("`merchant_request_id` = '" . $this->db->escape($merchantRequestId) . "'");
    }

    public function findByOrderId(int $orderId): array
    {
        $query = $this->db->query(
            "SELECT * FROM `" . DB_PREFIX . "alliance_fiscalization` WHERE `order_id` = '" . $orderId
            . "' ORDER BY `id` ASC"
        );

        return $query->rows;
    }

    public function upsert(array $data): array
    {
        if (empty($data['operation_id'])) {
            throw new \InvalidArgumentException('operation_id is required');
        }

        $now = date('Y-m-d H:i:s');
        $existing = $this->findByOperationId((string)$data['operation_id']);

        $sets = [];
        foreach (self::COLUMNS as $column) {
            if (!array_key_exists($column, $data)) {
                continue;
            }
            $sets[] = "`" . $column . "` = " . $this->quote($data[$column]);
        }
        $sets[] = "`updated_at` = '" . $now . "'";

        if ($existing) {
            $this->db->query(
                "UPDATE `" . DB_PREFIX . "alliance_fiscalization` SET " . implode(', ', $sets)
                . " WHERE `id` = '" . (int)$existing['id'] . "'"
            );
        } else {
            $sets[] = "`created_at` = '" . $now . "'";
            $this->db->query("INSERT INTO `" . DB_PREFIX . "alliance_fiscalization` SET " . implode(', ', $sets));
        }

        return $this->findByOperationId((string)$data['operation_id']) ?? [];
    }

    public function getProductAttributes(int $productId): array
    {
        $query = $this->db->query(
            "SELECT * FROM `" . DB_PREFIX . "alliance_fiscalization_product` WHERE `product_id` = '" . $productId . "'"
        );

        return $query->row ?: [];
    }

    public function saveProductAttributes(int $productId, array $values): void
    {
        $empty = true;
        foreach (['tax_code', 'classification_code', 'excise_labels', 'measurement_code'] as $field) {
            if (trim((string)($values[$field] ?? '')) !== '') {
                $empty = false;
            }
        }

        $this->deleteProductAttributes($productId);

        if ($empty) {
            return;
        }

        $taxCode = trim((string)($values['tax_code'] ?? ''));
        $this->db->query(
            "INSERT INTO `" . DB_PREFIX . "alliance_fiscalization_product` SET `product_id` = '" . $productId . "', "
            . "`tax_code` = " . ($taxCode === '' ? 'NULL' : "'" . (int)$taxCode . "'") . ", "
            . "`classification_code` = " . $this->quote(trim((string)($values['classification_code'] ?? '')) ?: null) . ", "
            . "`excise_labels` = " . $this->quote(trim((string)($values['excise_labels'] ?? '')) ?: null) . ", "
            . "`measurement_code` = " . $this->quote(trim((string)($values['measurement_code'] ?? '')) ?: null)
        );
    }

    public function deleteProductAttributes(int $productId): void
    {
        $this->db->query(
            "DELETE FROM `" . DB_PREFIX . "alliance_fiscalization_product` WHERE `product_id` = '" . $productId . "'"
        );
    }

    private function findOne(string $where): ?array
    {
        $query = $this->db->query(
            "SELECT * FROM `" . DB_PREFIX . "alliance_fiscalization` WHERE " . $where . " LIMIT 1"
        );

        return $query->row ?: null;
    }

    private function quote($value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        return "'" . $this->db->escape((string)$value) . "'";
    }
}
