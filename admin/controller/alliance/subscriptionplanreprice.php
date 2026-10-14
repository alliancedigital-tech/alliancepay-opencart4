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
 * Class Subscriptionplanreprice.
 */
class Subscriptionplanreprice extends Controller
{
    private const MIN_LEAD_TIME_DAYS = 30;

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
        if (!$this->user->hasPermission('access', 'extension/alliance/alliance/subscriptionplanreprice')) {
            $this->response->redirect($this->url->link('error/permission', 'user_token=' . $this->getUserToken()));
            return;
        }

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

        $this->document->setTitle($this->language->get('heading_title_reprice'));

        $currentRules = json_decode($plan['rules'], true) ?: [];
        foreach ($currentRules as &$rule) {
            $rule['amountDisplay'] = number_format(
                ($rule['coinAmount'] ?? 0) / 100,
                2,
                '.',
                ''
            );
        }
        unset($rule);

        $data['plan'] = $plan;
        $data['current_rules'] = $currentRules;
        $data['min_lead_time_days'] = self::MIN_LEAD_TIME_DAYS;
        $data['min_applies_from'] = date('Y-m-d', strtotime('+' . self::MIN_LEAD_TIME_DAYS . ' days'));
        $data['help_reprice_scope'] = sprintf(
            $this->language->get('help_reprice_scope'),
            (int)$plan['active_subscriptions_count']
        );
        $data['history'] = $this->model_extension_alliance_payment_subscriptionplan->getVersionHistory($subscriptionPlanId);
        $data['action'] = $this->url->link(
            'extension/alliance/alliance/subscriptionplanreprice|save',
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
        $data['heading_title'] = $this->language->get('heading_title_reprice');
        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/alliance/alliance/subscription_plan_reprice', $data));
    }

    /**
     * @return void
     */
    public function save(): void
    {
        $json = [];

        if (!$this->user->hasPermission('modify', 'extension/alliance/alliance/subscriptionplanreprice')) {
            $json['error'] = $this->language->get('error_permission');
            $this->respondJson($json);
            return;
        }

        $post = $this->request->post;
        $subscriptionPlanId = (int)($post['subscription_plan_id'] ?? 0);
        $plan = $this->model_extension_alliance_payment_subscriptionplan->getSubscriptionPlan($subscriptionPlanId);

        if (empty($plan)) {
            $json['error'] = $this->language->get('error_required_fields');
            $this->respondJson($json);
            return;
        }

        if (empty($post['notified_confirmed'])) {
            $json['error'] = $this->language->get('error_notification_not_confirmed');
            $this->respondJson($json);
            return;
        }

        $appliesFrom = (string)($post['applies_from'] ?? '');
        $minAllowed = strtotime('+' . self::MIN_LEAD_TIME_DAYS . ' days');

        if (!$appliesFrom || strtotime($appliesFrom) < $minAllowed) {
            $json['error'] = sprintf($this->language->get('error_lead_time'), self::MIN_LEAD_TIME_DAYS);
            $this->respondJson($json);
            return;
        }

        $currency = 'UAH';

        try {
            $newRules = $this->buildRulesPayload($post['rules'] ?? [], $currency);
        } catch (\InvalidArgumentException $e) {
            $json['error'] = $e->getMessage();
            $this->respondJson($json);
            return;
        }

        try {
            $subscriptionEcom = new SubscriptionEcom($this->registry);

            $updateResponse = $subscriptionEcom->updatePlan([
                'planId' => $plan['provider_plan_id'],
                'merchantId' => $plan['merchant_id'],
                'rules' => $newRules,
                'appliesDateTimeFrom' => $appliesFrom,
            ]);

            $newVersionId = $updateResponse['versionId'] ?? $plan['version_id'];
            $subscriptionEcom->setInfiniteVersion((string)$plan['provider_plan_id'], (string)$newVersionId);

            $this->model_extension_alliance_payment_subscriptionplan->reprice(
                $subscriptionPlanId,
                $newRules,
                (string)$newVersionId,
                $appliesFrom,
                (string)($post['reason'] ?? ''),
                (int)$this->user->getId()
            );

            $this->model_extension_alliance_payment_subscriptionplan->addAuditLog(
                $subscriptionPlanId,
                $plan['product_id'],
                (int)$this->user->getId(),
                'plan_repriced',
                ['applies_from' => $appliesFrom, 'active_subscriptions_count' => $plan['active_subscriptions_count']]
            );

            $json['success'] = $this->language->get('text_success');
        } catch (Throwable $e) {
            $json['error'] = $this->language->get('error_reprice_failed') . ': ' . $e->getMessage();
        }

        $this->respondJson($json);
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
