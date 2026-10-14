<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace Opencart\Catalog\Controller\Extension\Alliance\Payment\Event;

/**
 * Class Subscriptionproductoptions.
 */
class Subscriptionproductoptions extends \Opencart\System\Engine\Controller
{
    /**
     * @param string $route
     * @param array $data
     * @param string $output
     * @return void
     */
    public function afterProductView(string &$route, array &$data, string &$output): void
    {
        $productId = (int)($this->request->get['product_id'] ?? 0);

        if (!$productId) {
            return;
        }

        $this->load->model('extension/alliance/payment/subscription');
        $this->load->language('extension/alliance/payment/alliance');

        $plans = $this->model_extension_alliance_payment_subscription->getActivePlansForProduct($productId);

        if (!$plans) {
            return;
        }

        $optionsHtml = '<option value="">'
            . htmlspecialchars($this->language->get('text_one_time_purchase'), ENT_QUOTES)
            . '</option>';

        foreach ($plans as $plan) {
            $optionsHtml .= '<option value="' . (int)$plan['subscription_plan_id'] . '">'
                . htmlspecialchars($plan['name'], ENT_QUOTES) . '</option>';
        }

        $selectHtml = '<div class="mb-3">'
            . '<label class="form-label" for="alliance-input-subscription">'
            . htmlspecialchars($this->language->get('text_subscription_options'), ENT_QUOTES)
            . '</label>'
            . '<select name="alliance_subscription_plan_id" id="alliance-input-subscription" class="form-select">'
            . $optionsHtml
            . '</select>'
            . '</div>';

        $updated = preg_replace(
            '/(<form[^>]*id="form-product"[^>]*>)/',
            '$1' . $selectHtml,
            $output,
            1
        );

        if ($updated !== null) {
            $output = $updated;
        }
    }
}
