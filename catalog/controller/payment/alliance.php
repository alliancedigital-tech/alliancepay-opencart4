<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace Opencart\Catalog\Controller\Extension\Alliance\Payment;

use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Opencart\System\Helper\Alliance\Ecom;
use Opencart\System\Helper\Alliance\SubscriptionEcom;
use Throwable;

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
        $this->load->model('extension/alliance/payment/subscription');
        $this->load->model('checkout/order');
        $this->load->model('account/transaction');
        $this->load->helper('extension/alliance/alliance/ecom');
        $this->load->helper('extension/alliance/alliance/subscriptionecom');
        $this->ecom = new Ecom($registry);
    }

    /**
     * @return string
     */
    public function index(): string
    {
        $data['language'] = $this->config->get('config_language');

        $cartProductIds = [];
        foreach ($this->cart->getProducts() as $product) {
            $cartProductIds[] = (int)$product['product_id'];
        }

        [$selectedProductId, ] = $this->resolveSelectedSubscription($cartProductIds);
        $data['is_subscription_order'] = $selectedProductId !== null;
        $data['guard_error'] = $this->mapGuardReason(
            $this->model_extension_alliance_payment_subscription->checkCartSubscriptionGuard(
                $cartProductIds,
                $selectedProductId,
                $this->customer->isLogged()
            )
        );

        return $this->load->view('extension/alliance/payment/alliance', $data);
    }

    /**
     * @return void
     */
    public function subscriptionSignup(): void
    {
        $responseData = [];
        $orderId = (int)($this->session->data['order_id'] ?? 0);
        $order = $this->model_checkout_order->getOrder($orderId);
        $orderProducts = $this->model_checkout_order->getProducts($orderId);
        $productIds = array_map(static fn($product) => (int)$product['product_id'], $orderProducts);

        [$selectedProductId, $plan] = $this->resolveSelectedSubscription($productIds);
        $guardError = $this->mapGuardReason(
            $this->model_extension_alliance_payment_subscription->checkCartSubscriptionGuard(
                $productIds,
                $selectedProductId,
                !empty($order['customer_id'])
            )
        );

        if ($guardError !== '') {
            $responseData['error'] = $guardError;
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($responseData));
            return;
        }

        if (empty($plan)) {
            $responseData['error'] = $this->language->get('error_something_went_wrong');
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($responseData));
            return;
        }

        $rulesSnapshot = json_decode($plan['rules'], true) ?: [];
        $subscriptionId = $this->model_extension_alliance_payment_subscription->createSubscription(
            $orderId,
            (int)$plan['subscription_plan_id'],
            !empty($order['customer_id']) ? (int)$order['customer_id'] : null,
            $rulesSnapshot
        );

        try {
            $merchantRequestId = uniqid();
            $subscriptionEcom = new SubscriptionEcom($this->registry);

            $result = $subscriptionEcom->createSubscriptionOrder([
                'planId' => $plan['provider_plan_id'],
                'merchantId' => $plan['merchant_id'],
                'merchantRequestId' => $merchantRequestId,
                'notificationUrl' => $this->getSubscriptionNotificationUrl(),
                'successUrl' => $this->config->get('payment_alliance_success_url'),
                'failUrl' => $this->config->get('payment_alliance_fail_url'),
                'statusPageType' => 'STATUS_TIMER_PAGE',
                'hppPaymentMethods' => ['CARD', 'APPLE_PAY', 'GOOGLE_PAY'],
                'directType' => 'REDIRECT',
                'customerData' => $this->buildSubscriptionCustomerData($order),
            ]);

            $providerOrderId = $result['hppOrderId'] ?? '';

            if (!$providerOrderId || empty($result['redirectUrl'])) {
                throw new \RuntimeException('Subscription order create response missing hppOrderId/redirectUrl');
            }

            $this->model_extension_alliance_payment_subscription->saveSubscriptionOrderCreated(
                $subscriptionId,
                $providerOrderId,
                $merchantRequestId
            );

            $this->model_extension_alliance_payment_subscription->updateSubscriptionStatus($subscriptionId, 'AWAITING_PAYMENT');
            $this->model_extension_alliance_payment_alliance->clearCart($this->session);

            $responseData['redirect'] = $result['redirectUrl'];
        } catch (Throwable $e) {
            $this->model_extension_alliance_payment_subscription->updateSubscriptionStatus($subscriptionId, 'ERROR');
            $responseData['error'] = $this->language->get('error_something_went_wrong');
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($responseData));
    }

    /**
     * @return bool
     */
    public function notifyCallback(): bool
    {
        $request = file_get_contents('php://input');
        $trimmed = trim($request);
        $dotCount = substr_count($trimmed, '.');
        $isToken = $trimmed !== '' && strpos($trimmed, ' ') === false;

        if ($isToken && $dotCount === 2) {
            try {
                $request_data = $this->ecom->verifyJwsToken($trimmed);
            } catch (\Throwable $e) {
                $this->response->addHeader($this->request->server['SERVER_PROTOCOL'] . ' 400 Bad Request');

                return false;
            }
        } else {
            $request_data = json_decode($trimmed, true);
        }

        if (!is_array($request_data)) {
            $this->response->addHeader($this->request->server['SERVER_PROTOCOL'] . ' 400 Bad Request');

            return false;
        }

        $alliance_order = $this->model_extension_alliance_payment_alliance->getAllianceOrder(
            $request_data['hppOrderId']
        );

        if (empty($alliance_order)) {
            return $this->handleSubscriptionCallback($request_data);
        }

        $order = $this->model_checkout_order->getOrder($alliance_order['order_id']);

        if (empty($order)) {
            return false;
        }

        $callbackOperation = $this->getCallbackOperation($request_data);

        if (!empty($callbackOperation) && !empty($callbackOperation['type'])) {
            $isRefund = $callbackOperation['type'] === Ecom::OPERATION_TYPE_REFUND;
            $purchaseOperationTypes = [
                Ecom::OPERATION_TYPE_PURCHASE,
                Ecom::OPERATION_TYPE_A2A,
                Ecom::OPERATION_TYPE_COMPLETION
            ];

            if (in_array($callbackOperation['type'], $purchaseOperationTypes) && !$isRefund) {
                $operationStatus = $this->getOperationStatus($callbackOperation);
                $status = !empty($operationStatus) ? $operationStatus : $request_data['orderStatus'];
            } elseif ($isRefund) {
                $status = $callbackOperation['status'] ?? '';
            } else {
                $status = '';
            }

            if (!$isRefund) {
                $request_data['operationId'] = $this->getPurchaseOperationId($callbackOperation);
            }

            if ($status !== '' && !$isRefund && $callbackOperation['type'] !== Ecom::OPERATION_TYPE_PREAUTH) {
                $this->handleOrderStatusChange($status, $alliance_order, $order);
            }

            $this->model_extension_alliance_payment_alliance->saveCallBack(
                $request_data,
                $alliance_order['order_id']
            );

            return true;
        }

        return false;
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
            Ecom::OPERATION_TYPE_A2A,
            Ecom::OPERATION_TYPE_PREAUTH,
            Ecom::OPERATION_TYPE_COMPLETION
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
            Ecom::OPERATION_TYPE_PREAUTH,
            Ecom::OPERATION_TYPE_COMPLETION,
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
            $data = $this->ecom->createCardHppOrder(
                $order,
                $this->config,
                $this->url,
                $order['total']
            );
            $data['currencyCode'] = Ecom::getCurrencyCode($order['currency_code']);
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

    /**
     * @param array $requestData
     * @return bool
     */
    private function handleSubscriptionCallback(array $requestData): bool
    {
        $providerOrderId = (string)($requestData['hppOrderId'] ?? '');

        if ($providerOrderId === '') {
            return false;
        }

        $subscription = $this->model_extension_alliance_payment_subscription->getSubscriptionByProviderOrderId($providerOrderId);

        if (empty($subscription)) {
            return false;
        }

        $callbackOperation = $this->getCallbackOperation($requestData);
        $operationId = (string)($callbackOperation['operationId'] ?? '');
        $status = (string)($callbackOperation['status'] ?? ($requestData['orderStatus'] ?? ''));
        $isRecurring = !empty($requestData['recurringTransactionType']);

        $this->model_extension_alliance_payment_subscription->saveLatestCallbackData(
            (int)$subscription['subscription_id'],
            $requestData
        );

        if ($isRecurring) {
            $providerScheduledPaymentId = (string)($requestData['scheduledPaymentId'] ?? '');
            $scheduledPayment = $this->model_extension_alliance_payment_subscription->findScheduledPayment(
                (int)$subscription['subscription_id'],
                $providerScheduledPaymentId,
                $operationId
            );

            $paymentData = [
                'provider_order_id' => $providerOrderId,
                'provider_scheduled_payment_id' => $providerScheduledPaymentId,
                'operation_id' => $operationId,
                'status' => $status,
            ];

            if (!empty($scheduledPayment)) {
                $this->model_extension_alliance_payment_subscription->updateScheduledPayment(
                    (int)$scheduledPayment['scheduled_payment_id'],
                    $paymentData
                );
            } else {
                $this->model_extension_alliance_payment_subscription->createScheduledPayment(
                    (int)$subscription['subscription_id'],
                    $paymentData
                );
            }

            return true;
        }

        if ($status === 'SUCCESS') {
            $this->handleOrderStatusChange('SUCCESS', ['order_id' => $subscription['order_id']], []);
            $this->model_extension_alliance_payment_subscription->updateSubscriptionStatus(
                (int)$subscription['subscription_id'],
                'ACTIVE'
            );
        } elseif ($status === 'PENDING' || $status === 'REQUIRED_3DS') {
            $this->handleOrderStatusChange('PENDING', ['order_id' => $subscription['order_id']], []);
        } elseif ($status === 'FAIL') {
            $this->handleOrderStatusChange('FAIL', ['order_id' => $subscription['order_id']], []);
            $this->model_extension_alliance_payment_subscription->updateSubscriptionStatus(
                (int)$subscription['subscription_id'],
                'ERROR'
            );
        }

        return true;
    }

    /**
     * @param array $productIds
     * @return array
     */
    private function resolveSelectedSubscription(array $productIds): array
    {
        foreach ($productIds as $productId) {
            $plan = $this->model_extension_alliance_payment_subscription->getSelectedSubscriptionPlan(
                $this->session,
                (int)$productId
            );

            if (!empty($plan)) {
                return [(int)$productId, $plan];
            }
        }

        return [null, []];
    }

    /**
     * @param string $reason
     * @return string
     */
    private function mapGuardReason(string $reason): string
    {
        if ($reason === 'guest') {
            return $this->language->get('error_subscription_guest_checkout');
        }

        if ($reason === 'mixed') {
            return $this->language->get('error_subscription_mixed_cart');
        }

        return '';
    }

    /**
     * @return string
     */
    private function getSubscriptionNotificationUrl(): string
    {
        return $this->url->link('extension/alliance/payment/alliance|notifyCallback', '', true);
    }

    /**
     * @param array $order
     * @return array
     */
    private function buildSubscriptionCustomerData(array $order): array
    {
        $isoCode2 = !empty($order['payment_iso_code_2'])
            ? $order['payment_iso_code_2']
            : ($order['shipping_iso_code_2'] ?? '');

        $this->load->helper('extension/alliance/alliance/alliancecountrycodeprovider');
        $countryCodeProvider = new \Opencart\System\Helper\Alliance\AllianceCountryCodeProvider();

        $customerData = [
            'senderFirstName' => $order['firstname'] ?? '',
            'senderLastName' => $order['lastname'] ?? '',
            'senderEmail' => $order['email'] ?? '',
            'senderCountry' => !empty($isoCode2) ? $countryCodeProvider->getCountryNumericCodeByAlpha2($isoCode2) : '',
            'senderRegion' => !empty($order['payment_zone']) ? $order['payment_zone'] : ($order['shipping_zone'] ?? ''),
            'senderCity' => !empty($order['payment_city']) ? $order['payment_city'] : ($order['shipping_city'] ?? ''),
            'senderStreet' => !empty($order['payment_address_1']) ? $order['payment_address_1'] : ($order['shipping_address_1'] ?? ''),
            'senderAdditionalAddress' => !empty($order['payment_address_2']) ? $order['payment_address_2'] : ($order['shipping_address_2'] ?? ''),
            'senderIp' => $order['ip'] ?? $this->request->server['REMOTE_ADDR'],
            'senderPhone' => $order['telephone'] ?? '',
            'senderZipCode' => !empty($order['payment_postcode']) ? $order['payment_postcode'] : ($order['shipping_postcode'] ?? ''),
        ];

        $customerData['senderCustomerId'] = !empty($order['customer_id'])
            ? (string)$order['customer_id']
            : 'guest_' . ($order['order_id'] ?? uniqid());

        return $this->ecom->validateCustomerData($customerData, $this->ecom->getValidationRules());
    }
}
