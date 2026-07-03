<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace Opencart\Catalog\Controller\Extension\Alliance\Payment;

use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Opencart\System\Helper\Alliance\Ecom;

/**
 * Class Alliance.
 */
class Alliance extends \Opencart\System\Engine\Controller
{
    public function __construct(\Opencart\System\Engine\Registry $registry)
    {
        parent::__construct($registry);

        $this->load->language('extension/alliance/payment/alliance');
        $this->load->model('extension/alliance/payment/alliance');
        $this->load->model('checkout/order');
        $this->load->model('account/transaction');
        $this->load->helper('extension/alliance/alliance/ecom');
        $this->ecom = new Ecom($registry);
    }

    /**
     * @return string
     */
    public function index(): string
    {
        $data['language'] = $this->config->get('config_language');

        return $this->load->view('extension/alliance/payment/alliance', $data);
    }

    /**
     * @return bool
     */
    public function notifyCallback(): bool
    {
        $request = file_get_contents('php://input');
        $request_data = json_decode($request, true);
        $alliance_order = $this->model_extension_alliance_payment_alliance->getAllianceOrder(
            $request_data['hppOrderId']
        );

        if (empty($alliance_order)) {
            return false;
        }

        $order = $this->model_checkout_order->getOrder($alliance_order['order_id']);

        if (empty($order)) {
            return false;
        }

        $hppPayType = $request_data['hppPayType'] ?? '';
        $callbackOperation = $this->getCallbackOperation($request_data);
        $isRefund = !empty($callbackOperation) && $callbackOperation['type'] === Ecom::OPERATION_TYPE_REFUND;
        $hppTypes = [
            Ecom::HPP_PAY_TYPE_PURCHASE,
            Ecom::HPP_PAY_TYPE_A2A
        ];

        if (in_array($hppPayType, $hppTypes) && !$isRefund) {
            $operationStatus = $this->getOperationStatus($callbackOperation);
            $status = !empty($operationStatus) ? $operationStatus : $request_data['orderStatus'];
        } elseif ($isRefund) {
            $status = $callbackOperation['status'] ?? '';
        } else {
            $status = '';
        }

        if (!empty($callbackOperation) && !$isRefund) {
            $request_data['operationId'] = $this->getPurchaseOperationId($callbackOperation);
        }

        if ($status !== '' && !$isRefund) {
            $this->handleOrderStatusChange($status, $alliance_order, $order);
        }

        $this->model_extension_alliance_payment_alliance->saveCallBack(
            $request_data,
            $alliance_order['order_id']
        );

        return true;
    }

    /**
     * @param string $status
     * @param array $allianceOrder
     * @param array $order
     * @return void
     */
    private function handleOrderStatusChange(string $status, array $allianceOrder, array $order): void
    {
        $orderId = $allianceOrder['order_id'];

        if ($status === 'SUCCESS') {
            $this->model_checkout_order->addHistory(
                $orderId,
                $this->config->get('payment_alliance_paid_status'),
                sprintf($this->language->get('text_transaction_success'), $orderId)
            );
        } elseif ($status === 'PENDING' || $status === 'REQUIRED_3DS') {
            $this->model_checkout_order->editOrderStatusId(
                $orderId,
                $this->config->get('payment_alliance_pending_status')
            );
        } elseif ($status === 'FAIL') {
            $this->model_checkout_order->addHistory(
                $orderId,
                $this->config->get('payment_alliance_error_status'),
                sprintf($this->language->get('text_transaction_fail'), $orderId)
            );
        }
    }

    /**
     * @param array $callbackOperation
     * @return string
     */
    private function getOperationStatus(array $callbackOperation): string
    {
        if (!empty($callbackOperation['status'])) {
            return $callbackOperation['status'];
        }

        return '';
    }

    /**
     * @param array $callbackOperation
     * @return string
     */
    private function getPurchaseOperationId(array $callbackOperation): string
    {
        $purchaseOperationTypes = [
            Ecom::OPERATION_TYPE_PURCHASE,
            Ecom::OPERATION_TYPE_A2A
        ];

        if (!empty($callbackOperation['operationId'])
            && in_array($callbackOperation['type'], $purchaseOperationTypes)
        ) {
            return $callbackOperation['operationId'];
        }

        return '';
    }

    /**
     * @param array $data
     * @return array
     */
    private function getCallbackOperation(array $data): array
    {
        $callbackOperation = [];
        $callbackOperationTypes = [
            Ecom::OPERATION_TYPE_PURCHASE,
            Ecom::OPERATION_TYPE_A2A,
            Ecom::OPERATION_TYPE_REFUND
        ];

        if (!empty($data['operation'])
            && isset($data['operation']['type'])
            && in_array($data['operation']['type'], $callbackOperationTypes)
        ) {
            $callbackOperation = $data['operation'];
        }

        return $callbackOperation;
    }

    /**
     * @return void
     */
    public function payment(): void
    {
        $responseData = [];
        $order = $this->model_checkout_order->getOrder($this->session->data['order_id']);

        try {
            $jwe_token = $this->ecom->authorizeByVirtualDevice(
                $this->config->get('payment_alliance_service_code_id')
            );
            $decrypt_auth = $this->ecom->decryptResponse($jwe_token);
            $data = $this->ecom->createCardHppOrder(
                $decrypt_auth,
                $order,
                $this->config,
                $this->url,
                $order['total']
            );
            $this->model_extension_alliance_payment_alliance->saveAllianceOrder(
                $data,
                $this->session->data['order_id']
            );
            $this->model_extension_alliance_payment_alliance->clearCart($this->session);

            $responseData['redirect'] = $data['redirectUrl'] ?? '';
        } catch (Exception $e) {
            $responseData['error'] = $this->language->get('error_something_went_wrong');
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($responseData));
    }
}
