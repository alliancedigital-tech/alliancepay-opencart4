<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace Opencart\Admin\Controller\Extension\Alliance\Alliance;

use Opencart\System\Engine\Controller;
use Opencart\System\Engine\Registry;
use Opencart\System\Helper\Alliance\Ecom;
use Opencart\System\Helper\Alliance\SubscriptionEcom;
use Throwable;

/**
 * Class Subscriptionplan.
 */
class Subscriptionplan extends Controller
{
    public function __construct(Registry $registry)
    {
        parent::__construct($registry);
        $this->load->language('extension/alliance/alliance/subscriptionplan');
        $this->load->model('extension/alliance/payment/subscriptionplan');
        $this->load->helper('extension/alliance/alliance/ecom');
        $this->load->helper('extension/alliance/alliance/subscriptionecom');
    }

    public function index(): void
    {
        $this->document->setTitle($this->language->get('heading_title'));

        $start = (int)($this->request->get['start'] ?? 0);
        $limit = 20;

        $data['plans'] = [];
        foreach ($this->model_extension_alliance_payment_subscriptionplan->getSubscriptionPlans($start, $limit) as $plan) {
            $rules = json_decode($plan['rules'], true) ?: [];
            $data['plans'][] = [
                'subscription_plan_id' => $plan['subscription_plan_id'],
                'name' => $plan['name'],
                'product_id' => $plan['product_id'],
                'status' => $plan['status'],
                'is_active' => (bool)$plan['is_active'],
                'active_subscriptions_count' => (int)$plan['active_subscriptions_count'],
                'rules_summary' => $this->summarizeRules($rules),
                'edit' => $this->url->link(
                    'extension/alliance/alliance/subscriptionplan|edit',
                    'user_token=' . $this->getUserToken() . '&subscription_plan_id=' . $plan['subscription_plan_id']
                ),
                'reprice' => $this->url->link(
                    'extension/alliance/alliance/subscriptionplanreprice',
                    'user_token=' . $this->getUserToken() . '&subscription_plan_id=' . $plan['subscription_plan_id']
                ),
            ];
        }

        $data['total'] = $this->model_extension_alliance_payment_subscriptionplan->getTotalSubscriptionPlans();
        $data['add'] = $this->url->link('extension/alliance/alliance/subscriptionplan|add', 'user_token=' . $this->getUserToken());
        $data['user_token'] = $this->getUserToken();
        $data['heading_title'] = $this->language->get('heading_title');
        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');
        $data['success'] = $this->getFlash('success');
        $data['error_warning'] = $this->getFlash('warning');

        $this->response->setOutput($this->load->view('extension/alliance/alliance/subscription_plan_list', $data));
    }

    public function add(): void
    {
        $this->renderForm([]);
    }

    public function edit(): void
    {
        $subscriptionPlanId = (int)($this->request->get['subscription_plan_id'] ?? 0);
        $plan = $this->model_extension_alliance_payment_subscriptionplan->getSubscriptionPlan($subscriptionPlanId);

        if (empty($plan)) {
            $this->response->redirect(
                $this->url->link(
                    'extension/alliance/alliance/subscriptionplan',
                    'user_token=' . $this->getUserToken()
                )
            );
            return;
        }

        $this->renderForm($plan, $plan['active_subscriptions_count'] > 0);
    }

    /**
     * @return void
     */
    public function save(): void
    {
        $json = [];

        if (!$this->user->hasPermission('modify', 'extension/alliance/alliance/subscriptionplan')) {
            $json['error'] = $this->language->get('error_permission');
            $this->respondJson($json);
            return;
        }

        $post = $this->request->post;
        $productId = (int)($post['product_id'] ?? 0);
        $categoryIndicator = trim((string)($post['category_indicator'] ?? ''));
        $templateId = !empty($post['template_id']) ? (int)$post['template_id'] : null;
        $currency = 'UAH';
        $name = trim((string)($post['name'] ?? ''));

        if (!$productId || !$name || empty($post['rules']) || !is_array($post['rules'])) {
            $json['error'] = $this->language->get('error_required_fields');
            $this->respondJson($json);
            return;
        }

        try {
            $rules = $this->buildRulesPayload($post['rules'], $currency);
        } catch (\InvalidArgumentException $e) {
            $json['error'] = $e->getMessage();
            $this->respondJson($json);
            return;
        }

        $merchantId = (string)$this->config->get('payment_alliance_merchant_id');
        $planKey = SubscriptionEcom::computePlanKey(
            $merchantId,
            $productId,
            $templateId,
            $categoryIndicator,
            $rules
        );

        $existing = $this->model_extension_alliance_payment_subscriptionplan->findByPlanKey($planKey);
        if (!empty($existing)) {
            // §4.8 dedup — do not create a duplicate at the provider.
            $json['success'] = $this->language->get('text_success_existing');
            $json['subscription_plan_id'] = $existing['subscription_plan_id'];
            $this->respondJson($json);
            return;
        }

        $localData = [
            'product_id' => $productId,
            'template_id' => $templateId,
            'plan_key' => $planKey,
            'category_indicator' => $categoryIndicator,
            'merchant_id' => $merchantId,
            'name' => $name,
            'description' => (string)($post['description'] ?? ''),
            'rules' => $rules,
        ];

        try {
            $subscriptionEcom = new SubscriptionEcom($this->registry);

            $createResponse = $subscriptionEcom->createPlan([
                'name' => $name,
                'description' => $localData['description'],
                'categorySubcategoryIndicator' => $categoryIndicator,
                'merchantId' => $merchantId,
                'rules' => $rules,
            ]);

            $planId = (string)($createResponse['planId'] ?? '');
            $versionId = (string)($createResponse['versionId'] ?? '');

            if (!$planId || !$versionId) {
                throw new \RuntimeException(
                    'Plan create response missing planId/versionId: ' . json_encode($createResponse)
                );
            }

            $localData['provider_plan_id'] = $planId;
            $localData['version_id'] = $versionId;

            $subscriptionEcom->setInfiniteVersion($planId, $versionId);

            $subscriptionPlanId = $this->model_extension_alliance_payment_subscriptionplan->createPlanLocal($localData);

            $this->model_extension_alliance_payment_subscriptionplan->addAuditLog(
                $subscriptionPlanId,
                $productId,
                (int)$this->user->getId(),
                'plan_created'
            );

            $json['success'] = $this->language->get('text_success');
            $json['subscription_plan_id'] = $subscriptionPlanId;
        } catch (Throwable $e) {
            $subscriptionPlanId = $this->model_extension_alliance_payment_subscriptionplan->createPlanError(
                $localData,
                $e->getMessage()
            );
            $this->model_extension_alliance_payment_subscriptionplan->addAuditLog(
                $subscriptionPlanId,
                $productId,
                (int)$this->user->getId(),
                'plan_create_failed',
                ['error' => $e->getMessage()]
            );
            $json['error'] = $this->language->get('error_plan_create_failed');
        }

        $this->respondJson($json);
    }

    /**
     * @return void
     */
    public function deactivateForNewCustomers(): void
    {
        $json = [];

        if (!$this->user->hasPermission('modify', 'extension/alliance/alliance/subscriptionplan')) {
            $json['error'] = $this->language->get('error_permission');
            $this->respondJson($json);
            return;
        }

        $subscriptionPlanId = (int)($this->request->post['subscription_plan_id'] ?? 0);

        if (!$subscriptionPlanId) {
            $json['error'] = $this->language->get('error_required_fields');
            $this->respondJson($json);
            return;
        }

        $this->model_extension_alliance_payment_subscriptionplan->deactivateLocalOnly($subscriptionPlanId);
        $this->model_extension_alliance_payment_subscriptionplan->addAuditLog(
            $subscriptionPlanId,
            null,
            (int)$this->user->getId(),
            'plan_deactivated_for_new_customers'
        );

        $json['success'] = $this->language->get('text_success');
        $this->respondJson($json);
    }

    /**
     * @param array $plan
     * @param bool $lockStructure
     * @return void
     */
    private function renderForm(array $plan, bool $lockStructure = false): void
    {
        $this->document->setTitle($this->language->get('heading_title'));

        $rules = !empty($plan['rules']) ? json_decode($plan['rules'], true) : [];
        foreach ($rules as &$rule) {
            $rule['amountDisplay'] = number_format(($rule['coinAmount'] ?? 0) / 100, 2, '.', '');
        }
        unset($rule);

        $data['plan'] = $plan;
        $data['rules'] = $rules;
        $data['lock_structure'] = $lockStructure;
        $data['active_subscriptions_text'] = sprintf(
            $this->language->get('text_active_subscriptions_count'),
            $plan['active_subscriptions_count'] ?? 0
        );
        $data['action'] = $this->url->link(
            'extension/alliance/alliance/subscriptionplan|save',
            'user_token=' . $this->getUserToken(),
            true
        );
        $data['back'] = $this->url->link(
            'extension/alliance/alliance/subscriptionplan',
            'user_token=' . $this->getUserToken()
        );
        $data['back_js'] = $this->url->link(
            'extension/alliance/alliance/subscriptionplan',
            'user_token=' . $this->getUserToken(),
            true
        );
        $data['user_token'] = $this->getUserToken();
        $data['heading_title'] = $this->language->get('heading_title');
        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/alliance/alliance/subscription_plan_form', $data));
    }

    /**
     * @param array $postRules
     * @param string $currency
     * @return array
     */
    private function buildRulesPayload(array $postRules, string $currency): array
    {
        $currencyNumeric = Ecom::getCurrencyCode($currency);
        $rules = [];

        foreach ($postRules as $row) {
            $from = (int)($row['payment_number_from'] ?? 0);
            $to = (int)($row['payment_number_to'] ?? 0);
            $intervalValue = (int)($row['interval_value'] ?? 0);
            $intervalUnit = (string)($row['interval_unit'] ?? '');
            $amount = (float)str_replace(',', '.', (string)($row['amount'] ?? '0'));

            if (!$from || !$to || $to < $from || !$intervalValue || $intervalUnit === '') {
                throw new \InvalidArgumentException($this->language->get('error_invalid_rule_row'));
            }

            $rules[] = [
                'paymentNumberFrom' => $from,
                'paymentNumberTo' => $to,
                'intervalValue' => $intervalValue,
                'intervalUnit' => $intervalUnit,
                'coinAmount' => (int)round($amount * 100),
                'currency' => (string)$currencyNumeric,
            ];
        }

        if (!$rules) {
            throw new \InvalidArgumentException($this->language->get('error_invalid_rule_row'));
        }

        return $rules;
    }

    /**
     * @param array $rules
     * @return string
     */
    private function summarizeRules(array $rules): string
    {
        $parts = [];
        foreach ($rules as $rule) {
            $amount = number_format(($rule['coinAmount'] ?? 0) / 100, 2, '.', ' ');
            $parts[] = ($rule['paymentNumberFrom'] ?? '?') . '-' . ($rule['paymentNumberTo'] ?? '?')
                . ': ' . $amount . ' / ' . ($rule['intervalValue'] ?? '') . ' ' . ($rule['intervalUnit'] ?? '');
        }

        return implode('; ', $parts);
    }

    /**
     * @param string $key
     * @return string
     */
    private function getFlash(string $key): string
    {
        $value = $this->session->data[$key] ?? '';
        unset($this->session->data[$key]);

        return $value;
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
