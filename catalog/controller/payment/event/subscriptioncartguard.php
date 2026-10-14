<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace Opencart\Catalog\Controller\Extension\Alliance\Payment\Event;

/**
 * Class Subscriptioncartguard.
 */
class Subscriptioncartguard extends \Opencart\System\Engine\Controller
{
    /**
     * @param ...$args
     * @return void
     */
    public function afterAddToCart(&...$args): void
    {
        $productId = (int)($this->request->post['product_id'] ?? 0);

        if (!$productId) {
            return;
        }

        $selectedPlanId = (int)($this->request->post['alliance_subscription_plan_id'] ?? 0);

        if (!$selectedPlanId) {
            unset($this->session->data['alliance_selected_subscription_plan_id']);
            return;
        }

        $this->load->model('extension/alliance/payment/subscription');

        $validPlanIds = array_column(
            $this->model_extension_alliance_payment_subscription->getActivePlansForProduct($productId),
            'subscription_plan_id'
        );

        if (in_array($selectedPlanId, array_map('intval', $validPlanIds), true)) {
            $this->session->data['alliance_selected_subscription_plan_id'] = $selectedPlanId;
        } else {
            unset($this->session->data['alliance_selected_subscription_plan_id']);
        }
    }
}
