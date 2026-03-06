<?php
namespace Opencart\Catalog\Model\Extension\Alliance\Payment;

use Opencart\System\Helper\Alliance\Db\RefundHistory;

class Alliance extends \Opencart\System\Engine\Model
{
    /**
     * getMethods
     *
     * @param  mixed $address
     * @return array
     */
    public function getMethods(array $address = []): array
    {
        $this->load->language("extension/alliance/payment/alliance");

        $option_data["alliance"] = [
            "code" => "alliance.alliance",
            "name" => $this->language->get("text_alliance_title"),
        ];

        return [
            "code" => "alliance",
            "name" => $this->language->get("heading_title"),
            "option" => $option_data,
            "sort_order" => $this->config->get("payment_alliance_sort_order"),
        ];
    }

    public function getAllianceOrder($hpp_order_id)
    {
        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "alliance_checkout_integration_order` WHERE `hpp_order_id` = '" . $hpp_order_id . "'");

        return $query->row;
    }

    public function saveAllianceOrder(array $data, int $order_id): void
    {
        $this->load->model('checkout/order');

        if ($data['orderStatus'] == "PENDING" || $data['orderStatus'] == 'REQUIRED_3DS'){
            $this->model_checkout_order->addHistory($order_id, $this->config->get("payment_alliance_pending_status"));
        }

        if ($data['orderStatus'] == "FAIL"){
            $this->model_checkout_order->addHistory($order_id, $this->config->get("payment_alliance_error_status"));
        }

        $sql = "INSERT INTO `" . DB_PREFIX . "alliance_checkout_integration_order` SET";

        $implode = [];

        if (!empty($order_id)) {
            $implode[] = "`order_id` = '" . $order_id . "'";
        }

        if (!empty($data['merchantRequestId'])) {
            $implode[] = "`merchant_request_id` = '" . $this->db->escape($data['merchantRequestId']) . "'";
        }

        if (!empty($data['hppOrderId'])) {
            $implode[] = "`hpp_order_id` = '" . $this->db->escape($data['hppOrderId']) . "'";
        }

        if (!empty($data['merchantId'])) {
            $implode[] = "`merchant_id` = '" . $this->db->escape($data['merchantId']) . "'";
        }

        if (!empty($data['coinAmount'])) {
            $implode[] = "`coin_amount` = '" . (int) $data['coinAmount'] . "'";
        }

        if (!empty($data['hppPayType'])) {
            $implode[] = "`hpp_pay_type` = '" . $this->db->escape($data['hppPayType']) . "'";
        }

        if (!empty($data['orderStatus'])) {
            $implode[] = "`order_status` = '" . $this->db->escape($data['orderStatus']) . "'";
        }

        if (!empty($data['paymentMethods'])) {
            $paymentMethods = json_encode($data['paymentMethods']);
            $implode[] = "`payment_methods` = '" . $this->db->escape($paymentMethods) . "'";
        }

        if (!empty($data['createDate'])) {
            $implode[] = "`create_date` = '" . $this->db->escape($data['createDate']) . "'";
        }

        if (!empty($data['expiredOrderDate'])) {
            $implode[] = "`expired_order_date` = '" . $this->db->escape($data['expiredOrderDate']) . "'";
        }

        if ($implode) {
            $sql .= implode(", ", $implode);
        }

        $this->db->query($sql);
    }

    public function saveCallBack(array $data, int $order_id): void
    {
        $this->load->model('checkout/order');

        if ($data['orderStatus'] == "PENDING" || $data['orderStatus'] == 'REQUIRED_3DS'){
            $this->model_checkout_order->addHistory($order_id, $this->config->get("payment_alliance_pending_status"));
        }

        if ($data['orderStatus'] == "FAIL"){
            $this->model_checkout_order->addHistory($order_id, $this->config->get("payment_alliance_error_status"));
        }

        if ($data['orderStatus'] == "SUCCESS"){
            $this->model_checkout_order->addHistory($order_id, $this->config->get("payment_alliance_paid_status"));
        }

        $data = $this->prepareOperations($data);

        $sql = "UPDATE `" . DB_PREFIX . "alliance_checkout_integration_order` SET";

        $implode = [];

        if (!empty($data['orderStatus'])) {
            $implode[] = "`order_status` = '" . $data['orderStatus'] . "'";
        }

        $implode[] = "`updated_at` = '" . $this->db->escape(date('Y-m-d H:i:s')) . "'";
        $implode[] = "`callback_data` = '" . $this->db->escape(json_encode($data)) . "'";
        $implode[] = "`is_callback_returned` = '" . $this->db->escape(1) . "'";

        if ($implode) {
            $sql .= implode(', ', $implode);
        }

        $sql .= " WHERE `order_id` = '" . $order_id . "'";

        $this->db->query($sql);

        if (!empty($data['operations'])) {
            $this->updateRefundOperation($data['operations'], $order_id);
        }
    }

    private function updateRefundOperation(array $operations, int $order_id): void
    {
        $this->load->helper('extension/alliance/alliance/refund/refundhistory');
        $historyHelper = new RefundHistory($this->registry);

        foreach ($operations as $operation) {
            if (isset($operation['type']) && $operation['type'] == 'REFUND') {
                $historyHelper->updateRefundHistory(
                    $operation,
                    $order_id
                );
            }
        }
    }

    private function prepareOperations(array $data)
    {
        $operations = [];
        $hppOrderId = $data['hppOrderId'] ?? $data['hpp_order_id'];
        $allainceOrder = $this->getAllianceOrder($hppOrderId);

        if (!empty($allainceOrder) && !empty($allainceOrder['callback_data'])) {
            $callbackData = json_decode($allainceOrder['callback_data'], true);
            if (!empty($callbackData['operations'])) {
                $operations = array_column($callbackData['operations'], null, 'operationId');
            }
        }

        if (!isset($data['operations']) && isset($data['operation'])) {
            if (!isset($operations[$data['operation']['operationId']])) {
                $operations[] = $data['operation'];
                unset($data['operation']);
            }
        } elseif (isset($data['operations'])) {
            foreach ($data['operations'] as $callbackOperation) {
                $operationId = '';
                if (isset($callbackOperation['operationId'])) {
                    $operationId = $callbackOperation['operationId'];
                } elseif (isset($callbackOperation['operation_id'])) {
                    $operationId = $callbackOperation['operation_id'];
                }
                if (!empty($operationId) && !isset($operations[$operationId])) {
                    $operations[] = $callbackOperation;
                }
            }
        }

        $data['operations'] = $operations;

        return $data;
    }

    public function clearCart(object $session): void
    {
        $this->cart->clear();

        unset($session->data['order_id']);
        unset($session->data['payment_method']);
        unset($session->data['payment_methods']);
        unset($session->data['shipping_method']);
        unset($session->data['shipping_methods']);
        unset($session->data['comment']);
        unset($session->data['agree']);
        unset($session->data['coupon']);
        unset($session->data['reward']);
    }

    public function cartTotalPrice()
    {
        $this->load->model('checkout/cart');

        $totals = [];
        $taxes = $this->cart->getTaxes();
        $total = 0;

        ($this->model_checkout_cart->getTotals)($totals, $taxes, $total);

        return $totals[array_key_last($totals)]['value'];
    }
}
