<?php

namespace Opencart\Admin\Controller\Extension\Alliance\Alliance;

use GuzzleHttp\Exception\GuzzleException;
use Opencart\System\Engine\Controller;
use Opencart\System\Engine\Registry;
use Opencart\System\Helper\Alliance\Config;

/**
 * Class Refund.
 */
class Refund extends Controller
{
    public function __construct(Registry $registry)
    {
        parent::__construct($registry);
        $this->load->model('sale/order');
        $this->load->model('extension/alliance/payment/refund');
        $this->load->language('extension/alliance/payment/alliance');
    }

    /**
     * @param string $route
     * @param array $args
     * @param mixed $output
     * @return void
     */
    public function injectRefundButton(string &$route, array &$args, mixed &$output): void
    {
        $this->load->language('extension/alliance/payment/alliance');

        $buttonHtml = '';
        $order_id = (int)$this->request->get['order_id'];
        $order_info = $this->model_sale_order->getOrder($order_id);
        $isRefunded = $this->model_extension_alliance_payment_refund->checkIfOrderRefunded((int) $order_id);

        if ($order_info['payment_method']['code'] === Config::ALLIANCE_PAYMENT_CODE) {
            $disabled = !$isRefunded ? 0 : 1;

            $text_refund = $this->language->get('button_refund');
            $text_complete = $this->language->get('text_refund_complete');
            $text_failed = $this->language->get('text_refund_failed');
            $text_partial_refund = $this->language->get('text_partial_refund_title');
            $text_button_partial_refund = $this->language->get('button_partial_refund');

            $buttonHtml = '
            <script>
                document.addEventListener("DOMContentLoaded", function() {
                    let isDisabled = Boolean(' . $disabled . ');
                    const container = document.querySelector(".page-header .float-end");
                    if (container) {
                        const btnPartial = document.createElement("button");
                        btnPartial.id = "button-partial-return";
                        btnPartial.setAttribute("data-toggle", "tooltip");
                        btnPartial.setAttribute("title", "' . $text_partial_refund . '");
                        btnPartial.className = "btn btn-warning";
                        btnPartial.style = "margin-right:5px;";
                        btnPartial.innerHTML = \'' . $text_button_partial_refund . '\';
                        const formData = new FormData();
                        formData.append("is_full_refund", "1");
                        formData.append("product_ids", []);
                        const btn = document.createElement("button");
                        btn.className = "btn btn-danger";
                        btn.style = "margin-right:5px;";
                        if (isDisabled) {
                            btn.disabled = true;
                            btnPartial.disabled = true;
                        }
                        btn.innerHTML = \'' . $text_refund . '\';
                        btn.addEventListener("click", function() {
                            const orderId = new URLSearchParams(window.location.search).get("order_id");
                            fetch("index.php?route=extension/alliance/alliance/refund|refund&user_token='
                                . $this->getUserToken() . '&order_id=" + orderId, {
                                    method: "POST",
                                    body: formData
                                 })
                                .then(r => r.json())
                                .then(res => {
                                    btn.disabled = true;
                                    alert(res.message || "' . $text_complete . '");
                                })
                                .catch(err => alert(res.message || "' . $text_failed . '"));
                        });
                        btnPartial.addEventListener("click", function() {
                            proceedPartialReturn();
                        });
                        container.prepend(btnPartial);
                        container.prepend(btn);
                    }
                });
            </script>';
        }

        $output = str_replace('</body>', $buttonHtml . '</body>', $output);
    }

    /**
     * @return void
     * @throws GuzzleException
     */
    public function refund(): void
    {
        $orderId = $this->request->get['order_id'] ?? 0;
        $isFullRefund = $this->request->post['is_full_refund'] == 1;
        $productIds = $this->request->post['product_ids'] ?? [];
        $refundShipping = $this->request->post['refund_shipping'] ?? 0;
        $data = $this->model_extension_alliance_payment_refund->refundOrder($orderId, $isFullRefund, $productIds, $refundShipping);
        $this->response->addHeader("Content-Type: application/json");
        $this->response->setOutput(json_encode($data));
    }

    /**
     * @return string
     */
    private function getUserToken(): string
    {
        return $this->session->data['user_token'];
    }
}
