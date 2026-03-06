<?php
namespace Opencart\Catalog\Controller\Extension\Alliance\Payment;

use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Opencart\System\Helper\Alliance\Ecom;

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

    public function index(): string
    {
        $data['language'] = $this->config->get('config_language');

        return $this->load->view('extension/alliance/payment/alliance', $data);
    }

    public function notifyCallback(): bool
    {
        $request = file_get_contents('php://input');
        $request_data = json_decode($request, true);
        $alliance_order = $this->model_extension_alliance_payment_alliance->getAllianceOrder($request_data['hppOrderId']);

        if (empty($alliance_order)) {
            return false;
        }

        $order = $this->model_checkout_order->getOrder($alliance_order['order_id']);

        if (empty($order)) {
            return false;
        }

        if ($request_data['orderStatus'] === 'SUCCESS') {
            $this->model_checkout_order->addHistory(
                $alliance_order['order_id'],
                $this->config->get('payment_alliance_paid_status'),
                sprintf($this->language->get('text_transaction_success'), $alliance_order['order_id'])
            );
        }

        if ($request_data['orderStatus'] === 'PENDING' || $request_data['orderStatus'] === 'REQUIRED_3DS') {
            $this->model_checkout_order->editOrderStatusId(
                $alliance_order['order_id'],
                $this->config->get('payment_alliance_pending_status')
            );
        }

        if ($request_data['orderStatus'] === 'FAIL') {
            $this->model_checkout_order->addHistory(
                $alliance_order['order_id'],
                $this->config->get('payment_alliance_error_status'),
                sprintf($this->language->get('text_transaction_fail'), $alliance_order['order_id'])
            );
        }

        $this->model_extension_alliance_payment_alliance->saveCallBack(
            $request_data,
            $alliance_order['order_id']
        );

        return true;

    }

    /**
     * @throws GuzzleException
     * @throws Exception
     */
    public function payment(): void
    {
        $this->load->model('checkout/order');
        $ecom = new Ecom($this->registry);

        $order = $this->model_checkout_order->getOrder($this->session->data['order_id']);

        try {
            $jwe_token = $this->ecom->authorizeByVirtualDevice(
                $this->config->get('payment_alliance_service_code_id')
            );
            $decrypt_auth = $this->ecom->decryptResponse($jwe_token);
            $data = $this->ecom->createCardHppOrder(
                $decrypt_auth,
                $this->session,
                $this->config,
                $this->url,
                $order['total']
            );
            $this->model_extension_alliance_payment_alliance->saveAllianceOrder(
                $data,
                $this->session->data['order_id']
            );
            $this->model_extension_alliance_payment_alliance->clearCart($this->session);
        } catch (Exception $e) {
            $json['error'] = $this->language->get('error_something_went_wrong');
        }

        $json['redirect'] = $data['redirectUrl'] ?? '';

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
}
