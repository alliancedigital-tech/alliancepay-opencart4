<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace Opencart\Admin\Controller\Extension\Alliance\Event;

use Opencart\System\Engine\Controller;

/**
 * Class Order.
 */
class Order extends Controller
{
    /**
     * @param string $route
     * @param array $args
     * @param string $output
     * @return void
     */
    public function orderInfoAfter(string &$route, array &$args, string &$output): void
    {
        $this->load->language('extension/alliance/payment/alliance');
        $this->load->model('extension/alliance/payment/alliance');

        $order_id = (int)$this->request->get['order_id'];

        $isA2ACanBeRefund = $this->model_extension_alliance_payment_alliance
            ->checkIfA2AOrderCanBeRefunded($order_id);

        if (!$isA2ACanBeRefund) {
            return;
        }

        $returned_products = [];
        $returned_query = $this->db->query(
            "SELECT product_id FROM `" . DB_PREFIX . "return` WHERE order_id = '" . $order_id . "'"
        );
        if ($returned_query->num_rows) {
            foreach ($returned_query->rows as $row) {
                $returned_products[] = $row['product_id'];
            }
        }

        $shipping_match = $this->language->get('text_shipping_match')
            ?: 'shipping';

        $is_alliance_refunded = $this->model_extension_alliance_payment_alliance
            ->isShippingReturnedForOrder($order_id, $shipping_match);

        $search_header = '<td class="text-start">' . $args['column_product'] . '</td>';
        $add_header = '<td class="text-center" style="width: 1px;"><i class="fa-solid fa-undo" title="'
            . $this->language->get('text_select_for_return') . '"></i></td>' . "\n" . $search_header;
        $output = str_replace($search_header, $add_header, $output);

        if (isset($args['order_products'])) {
            foreach ($args['order_products'] as $product) {
                $search_product = '<td class="text-start"><a href="index.php?route=catalog/product.form&user_token='
                    . $this->session->data['user_token'] . '&product_id=' . $product['product_id']
                    . '" target="_blank">' . $product['name'] . '</a>';

                if (in_array($product['order_product_id'], $returned_products)) {
                    $add_product = '<td class="text-center"><i class="fa-solid fa-undo text-danger" title="'
                        . $this->language->get('text_already_returned') . '"></i></td>';
                } else {
                    $add_product = '<td class="text-center"><input type="checkbox" name="selected_return[]" value="'
                        . $product['order_product_id'] . '" class="form-check-input return-checkbox" /></td>';
                }

                $replace_product = $add_product . "\n" . $search_product;
                $pattern = '/' . preg_quote($search_product, '/') . '/';
                $output = preg_replace($pattern, $replace_product, $output, 1);
            }
        }

        $refund_html = '<tr><td class="text-start" colspan="4"><div class="form-check">';
        if ($is_alliance_refunded) {
            $refund_html .= '<i class="fa-solid fa-undo text-danger" title="'
                . $this->language->get('text_already_returned')
                . '"></i> <strong>' . $this->language->get('text_order_fully_refunded') . '</strong>';
        } else {
            $refund_html .= '<input type="checkbox" id="refund-shipping" value="1" class="form-check-input" /> '
                . '<label class="form-check-label" for="refund-shipping"><strong>'
                . $this->language->get('text_refund_shipping_cost') . '</strong></label>';
        }
        $refund_html .= '</div></td></tr>';

        $output = str_replace('<tfoot>', '<tfoot>' . $refund_html, $output);

        $route_url = 'index.php?route=extension/alliance/alliance/refund|refund&user_token='
            . $this->session->data['user_token'] . '&order_id=' . $order_id;

        $js = '
        <script type="text/javascript">
        function proceedPartialReturn() {
            var selected = [];
            var refund_shipping = $("#refund-shipping").is(":checked") ? 1 : 0;
            $(".return-checkbox:checked").each(function() {
                selected.push($(this).val());
            });

            if (selected.length == 0) {
                alert("' . $this->language->get('text_please_select_products') . '");
                return;
            }

            if (confirm("' . $this->language->get('text_confirm_return_requests') . '")) {
                $.ajax({
                    url: "' . $route_url . '",
                    type: "post",
                    data: { product_ids: selected, is_full_refund: false, refund_shipping: refund_shipping },
                    dataType: "json",
                    success: function(json) {
                        if (json["success"]) { alert(json["message"]); location.reload(); }
                        if (json["error"]) { alert(json["message"]); }
                    }
                });
            }
        }
        </script>';

        $output = str_replace('</footer>', '</footer>' . "\n" . $js, $output);
    }
}
