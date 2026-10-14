<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace Opencart\Admin\Model\Extension\Alliance\Payment;

use Opencart\System\Helper\Alliance\Ecom;

/**
 * Class Alliance.
 */
class Alliance extends \Opencart\System\Engine\Model
{

    /**
     * @param int $order_id
     * @return array
     */
    public function getAllianceOrderCallback(int $order_id): array
    {
        $query = $this->db->query(
            "SELECT callback_data FROM `" . DB_PREFIX . "alliance_checkout_integration_order`"
            . " WHERE `order_id` = '" . (int)$order_id . "'"
        );

        return !empty($query->row['callback_data']) ? json_decode($query->row['callback_data'], true) : [];
    }

    /**
     * @param int $order_id
     * @return array
     */
    public function getAllianceOrder(int $order_id): array
    {
        $query = $this->db->query(
            "SELECT * FROM `" . DB_PREFIX . "alliance_checkout_integration_order`"
            . " WHERE `order_id` = '" . (int)$order_id . "'"
        );

        return $query->row;
    }

    /**
     * @param array $data
     * @param array $e_com_response
     * @param bool $is_error
     * @param bool $isRefunded
     * @return array
     */
    public function fillOperationStatusData(
        array &$data,
        array $e_com_response,
        bool  $is_error,
        bool  $isRefunded = false,
        array $allianceOrder = [],
    ): array
    {
        $data['is_error'] = $is_error;

        if ($e_com_response) {
            $amount = $this->getCoinAmountFromCallbackData($e_com_response);

            if ($is_error) {
                $data['msg_type'] = $e_com_response['msgType'];
            } else {
                $data['is_refund'] = $isRefunded;
                $data['coin_amount'] = $this->convertCoinAmount($amount);

                $conversionRate = (float)($allianceOrder['conversion_rate'] ?? 0);
                $currencyCode = (int)($allianceOrder['currency_code'] ?? 0);
                $currencyName = Ecom::getCurrencyName($currencyCode);

                $amountInUah = $amount / 100;

                if ($conversionRate > 0 && $currencyName !== 'UAH') {
                    $data['has_currency_conversion'] = true;
                    $data['coin_amount_uah'] = number_format($amountInUah, 2, ',', ' ') . ' UAH';
                    $data['coin_amount_currency'] = number_format($amountInUah / $conversionRate, 2, ',', ' ') . ' ' . $currencyName;
                    $data['currency_name'] = $currencyName;
                } else {
                    $data['has_currency_conversion'] = false;
                    $data['coin_amount_uah'] = number_format($amountInUah, 2, ',', ' ') . ' UAH';
                    $data['coin_amount_currency'] = '';
                    $data['currency_name'] = '';
                }

                $data['ecom_order_id'] = $e_com_response['ecomOrderId'] ?? $e_com_response['ecom_order_id'] ?? '';
                $data['status_url'] = $e_com_response['statusUrl'] ?? $e_com_response['status_url'] ?? '';
                $data['merchant_id'] = $e_com_response['merchantId'] ?? $e_com_response['merchant_id'] ?? '';
                $data['hpp_order_id'] = $e_com_response['hppOrderId'] ?? $e_com_response['hpp_order_id'] ?? '';
                $data['hpp_pay_type'] = $e_com_response['hppPayType'] ?? $e_com_response['hpp_pay_type'] ?? '';
                $data['expired_order_date'] = $e_com_response['expiredOrderDate'] ?? $e_com_response['expired_order_date'] ?? '';
                $data['order_status'] = $e_com_response['orderStatus'] ?? $e_com_response['order_status'] ?? '';
                $data['create_date'] = $e_com_response['createDate'] ?? $e_com_response['create_date'] ?? '';
            }
        }

        return $data;
    }

    /**
     * @param int $order_id
     * @return bool
     */
    public function checkIfA2AOrderCanBeRefunded(int $order_id): bool
    {
        $allianceOrder = $this->getAllianceOrder($order_id);

        if (empty($allianceOrder)) {
            return true;
        }

        $decodedCallbackData = json_decode($allianceOrder['callback_data'] ?? '', true);

        if (!isset($decodedCallbackData['operations']) || !is_array($decodedCallbackData['operations'])) {
            return true;
        }

        foreach ($decodedCallbackData['operations'] as $operation) {
            if (($operation['type'] ?? '') === 'ACCOUNT_2_ACCOUNT'
                && ($operation['status'] ?? '') === 'SUCCESS'
                && ($operation['transactionType'] ?? null) === 102
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param int $order_id
     * @param string $shippingMatchText
     * @return bool
     */
    public function isShippingReturnedForOrder(int $order_id, string $shippingMatchText): bool
    {
        $query = $this->db->query(
            "SELECT return_id FROM `" . DB_PREFIX . "return`"
            . " WHERE `order_id` = '" . (int)$order_id . "'"
            . " AND `comment` LIKE CONCAT('%', '" . $this->db->escape($shippingMatchText) . "', '%')"
        );

        return $query->num_rows > 0;
    }

    /**
     * @param int|float $amount
     * @return string
     */
    private function convertCoinAmount($amount): string
    {
        return number_format($amount / 100, 2, ',', '');
    }

    /**
     * @param array $data
     * @return int
     */
    private function getCoinAmountFromCallbackData(array $data): int
    {
        $this->load->helper('extension/alliance/alliance/ecom');
        $purchaseOperationTypes = [
            Ecom::OPERATION_TYPE_PURCHASE,
            Ecom::OPERATION_TYPE_A2A,
            Ecom::OPERATION_TYPE_COMPLETION,
        ];
        $coinAmount = 0;

        if (!empty($data['operations'])) {
            foreach ($data['operations'] as $operation) {
                if (in_array($operation['type'], $purchaseOperationTypes)
                    && !empty($operation['coinAmount'])
                ) {
                    $coinAmount = $operation['coinAmount'];
                    break;
                }
            }
        }

        return $coinAmount;
    }
}
