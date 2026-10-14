<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace Opencart\Admin\Controller\Extension\Alliance\Alliance;

use Opencart\System\Engine\Controller;
use Opencart\System\Engine\Registry;
use Opencart\System\Helper\Alliance\SubscriptionEcom;
use Throwable;

/**
 * Class Subscription.
 */
class Subscription extends Controller
{
    public function __construct(Registry $registry)
    {
        parent::__construct($registry);
        $this->load->language('extension/alliance/alliance/subscription');
        $this->load->model('extension/alliance/payment/subscription');
        $this->load->helper('extension/alliance/alliance/subscriptionecom');
    }

    public function index(): void
    {
        $this->document->setTitle($this->language->get('heading_title'));

        $start = (int)($this->request->get['start'] ?? 0);
        $limit = 20;

        $data['subscriptions'] = [];
        foreach ($this->model_extension_alliance_payment_subscription->getSubscriptions($start, $limit) as $subscription) {
            $data['subscriptions'][] = [
                'subscription_id' => $subscription['subscription_id'],
                'order_id' => $subscription['order_id'],
                'plan_name' => $subscription['plan_name'],
                'status' => $subscription['status'],
                'provider_order_id' => $subscription['provider_order_id'],
                'view' => $this->url->link(
                    'extension/alliance/alliance/subscription|view',
                    'user_token=' . $this->getUserToken() . '&subscription_id=' . $subscription['subscription_id']
                ),
            ];
        }

        $data['total'] = $this->model_extension_alliance_payment_subscription->getTotalSubscriptions();
        $data['heading_title'] = $this->language->get('heading_title');
        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/alliance/alliance/subscription_list', $data));
    }

    /**
     * @return void
     */
    public function view(): void
    {
        $this->document->setTitle($this->language->get('heading_title_diagnostics'));

        $subscriptionId = (int)($this->request->get['subscription_id'] ?? 0);
        $subscription = $this->model_extension_alliance_payment_subscription->getSubscription($subscriptionId);

        if (empty($subscription)) {
            $this->response->redirect(
                $this->url->link(
                    'extension/alliance/alliance/subscription',
                    'user_token=' . $this->getUserToken()
                )
            );

            return;
        }

        $data['subscription'] = $subscription;
        $data['rules_snapshot'] = json_decode($subscription['rules_snapshot'], true) ?: [];
        $data['scheduled_payments'] = $this->model_extension_alliance_payment_subscription
            ->getScheduledPayments($subscriptionId);
        $data['provider_warning'] = $this->language->get('error_provider_read_endpoint_unconfirmed');
        $data['provider_operations'] = [];

        $data['trigger_url'] = $this->url->link(
            'extension/alliance/alliance/subscription|triggerRecurring',
            'user_token=' . $this->getUserToken(),
            true
        );
        $data['cancel_url'] = $this->url->link(
            'extension/alliance/alliance/subscription|cancel',
            'user_token=' . $this->getUserToken(),
            true
        );
        $data['back'] = $this->url->link(
            'extension/alliance/alliance/subscription',
            'user_token=' . $this->getUserToken()
        );
        $data['heading_title'] = $this->language->get('heading_title_diagnostics');
        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/alliance/alliance/subscription_diagnostics', $data));
    }

    /**
     * §4.4: ordinary `modify` permission, single-subscription blast radius only.
     *
     * @return void
     */
    public function triggerRecurring(): void
    {
        $json = [];

        if (!$this->user->hasPermission('modify', 'extension/alliance/alliance/subscription')) {
            $json['error'] = $this->language->get('error_permission');
            $this->respondJson($json);
            return;
        }

        $scheduledPaymentId = (int)($this->request->post['scheduled_payment_id'] ?? 0);
        $scheduledPayment = $this->model_extension_alliance_payment_subscription->getScheduledPayment($scheduledPaymentId);

        if (empty($scheduledPayment)) {
            $json['error'] = $this->language->get('error_required_fields');
            $this->respondJson($json);
            return;
        }

        try {
            $subscriptionEcom = new SubscriptionEcom($this->registry);
            $result = $subscriptionEcom->triggerRecurringPayment([
                'hppOrderId' => $scheduledPayment['provider_order_id'],
                'scheduledPaymentId' => $scheduledPayment['provider_scheduled_payment_id'],
                'merchantId' => $this->config->get('payment_alliance_merchant_id'),
                'merchantRequestId' => uniqid(),
            ]);

            $operationId = (string)($result['operationId'] ?? '');
            $this->model_extension_alliance_payment_subscription->recordManualTriggerResult(
                $scheduledPaymentId,
                (string)($result['status'] ?? 'PROCESSING'),
                $operationId
            );

            $json['success'] = $this->language->get('text_success');
        } catch (Throwable $e) {
            $json['error'] = $this->language->get('error_manual_trigger_failed') . ': ' . $e->getMessage();
        }

        $this->respondJson($json);
    }

    /**
     * @return void
     */
    public function cancel(): void
    {
        $json = [];

        if (!$this->user->hasPermission('modify', 'extension/alliance/alliance/subscription')) {
            $json['error'] = $this->language->get('error_permission');
            $this->respondJson($json);
            return;
        }

        $subscriptionId = (int)($this->request->post['subscription_id'] ?? 0);
        $subscription = $this->model_extension_alliance_payment_subscription->getSubscription($subscriptionId);

        if (empty($subscription) || empty($subscription['provider_order_id'])) {
            $json['error'] = $this->language->get('error_required_fields');
            $this->respondJson($json);
            return;
        }

        try {
            $subscriptionEcom = new SubscriptionEcom($this->registry);
            $subscriptionEcom->cancelSubscriptionOrder([
                'hppOrderId' => $subscription['provider_order_id'],
                'merchantId' => $this->config->get('payment_alliance_merchant_id'),
                'merchantRequestId' => uniqid(),
            ]);

            $this->model_extension_alliance_payment_subscription->cancelSubscriptionLocal(
                $subscription['provider_order_id']
            );

            $json['success'] = $this->language->get('text_success');
        } catch (Throwable $e) {
            $json['error'] = $this->language->get('error_cancel_failed') . ': ' . $e->getMessage();
        }

        $this->respondJson($json);
    }

    /**
     * @param array $json
     * @return void
     */
    private function respondJson(array $json): void
    {
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * @return string
     */
    private function getUserToken(): string
    {
        return $this->session->data['user_token'];
    }
}
