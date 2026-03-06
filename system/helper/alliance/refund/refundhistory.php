<?php

declare(strict_types=1);

namespace Opencart\System\Helper\Alliance\Db;

use Opencart\System\Engine\Registry;

/**
 * Class RefundHistory.
 */
class RefundHistory
{
    private $registry;
    public function __construct(Registry $registry)
    {
        $registry->load->helper('extension/alliance/alliance/db/refundhistorymapper');
        $this->registry = $registry;
    }

    /**
     * @param array $data
     * @param int $orderId
     * @return void
     */
    public function updateRefundHistory(array $data, int $orderId): void
    {
        if (!empty($data) && !empty($orderId)) {
            $operationExist = $this->checkIfOperationExist($data['operationId']);
            if ($operationExist) {
                $sql = "UPDATE `" . DB_PREFIX . "alliance_integration_order_refund` SET";
            } else {
                $sql = "INSERT INTO `" . DB_PREFIX . "alliance_integration_order_refund` SET";
            }

            $implode = [];
            $implode[] = "`order_id` = '" . $orderId . "'";

            foreach (RefundHistoryMapper::REFUND_DATA_HISTORY_FIELDS_MAPPING as $key => $dbField) {
                if (empty($data[$key])) {
                    continue;
                }
                $value = in_array($key, RefundHistoryMapper::JSON_ENCODED_FIELDS_LIST)
                    ? json_encode($data[$key])
                    : (string) $data[$key];
                $implode[] = sprintf("`%s` = '%s'", $dbField, $this->registry->db->escape($value));
            }

            if ($implode) {
                $sql .= implode(", ", $implode);
            }

            if ($operationExist) {
                $sql .= " WHERE `operation_id` = '" . $data['operationId'] . "'";
            }

            $this->registry->db->query($sql);
        }
    }

    public function checkIfOperationExist(string $operationId): bool
    {
        $query = $this->registry->db->query(
            "SELECT * FROM `" . DB_PREFIX . "alliance_integration_order_refund` WHERE `operation_id` = '"
            . $operationId . "'"
        );

        return $query->num_rows > 0;
    }
}
