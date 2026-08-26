<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace Opencart\Admin\Controller\Extension\Alliance\Payment;

use Opencart\System\Engine\Registry;
use Opencart\System\Helper\Alliance\Config;
use Opencart\System\Helper\Alliance\Ecom;
use Opencart\System\Library\Extension\Alliance\Http\ResponseDecoder;

/**
 * Class Alliance.
 */
class Alliance extends \Opencart\System\Engine\Controller
{
    public $version = '1.4.0';

    private array $events = [
        [
            'description' => 'Order info',
            'trigger' => 'admin/view/sale/order_info/before',
            'action' => 'extension/alliance/payment/alliance|operationsEvent',
            'event_code' => 'alliance_operations',
            'sort_order' => 1,
        ],
        [
            'description' => 'Fix session order id if its empty after addOrder method',
            'trigger' => 'catalog/model/checkout/order/addOrder/after',
            'action' => 'extension/alliance/payment/event/fix_session_order_id|afterAddOrder',
            'event_code' => 'alliance_fix_session_order_id',
            'sort_order' => 0,
        ],
        [
            'description' => 'Add refund button to order info',
            'trigger' => 'admin/view/sale/order_info/after',
            'action' => 'extension/alliance/alliance/refund|injectRefundButton',
            'event_code' => 'alliance_add_refund_button_order_info',
            'sort_order' => 2,
        ],
        [
            'description' => 'Add completion button to order info',
            'trigger' => 'admin/view/sale/order_info/after',
            'action' => 'extension/alliance/payment/alliance|injectCompletionButton',
            'event_code' => 'alliance_add_completion_button_order_info',
            'sort_order' => 3,
        ],
        [
            'description' => 'Add partial refund button to order info',
            'trigger' => 'admin/view/sale/order_info/after',
            'action' => 'extension/alliance/event/order|orderInfoAfter',
            'event_code' => 'alliance_order_info_partial_refund',
            'sort_order' => 1,
        ],
    ];

    public function index(): void
    {
        $this->load->language('extension/alliance/payment/alliance');
        $this->load->model('setting/setting');
        $this->load->model('extension/alliance/payment/data');
        $this->document->setTitle($this->language->get('heading_title'));

        $installed_version = $this->config->get('payment_alliance_version');

        if (!$installed_version
            || version_compare($installed_version, $this->version, '<')
        ) {
            $this->upgrade();
        }

        if (
            $this->request->server['REQUEST_METHOD'] == 'POST' &&
            $this->request->post['action'] === 'save'
        ) {
            $post = $this->request->post;
            $catalog_url = $this->config->get('config_url') ?: HTTP_CATALOG;
            $defaults = [
                'payment_alliance_success_url' => $catalog_url . 'index.php?route=checkout/success',
                'payment_alliance_fail_url' => $catalog_url . 'index.php?route=checkout/failure',
                'payment_alliance_version' => $this->version,
            ];

            foreach ($defaults as $key => $value) {
                if (empty($post[$key])) {
                    $post[$key] = $value;
                }
            }

            $this->model_setting_setting->editSetting('payment_alliance', $post);

            $this->session->data['success'] = $this->language->get(
                'text_success'
            );
            $this->response->redirect(
                $this->url->link(
                    'extension/alliance/payment/alliance',
                    'user_token=' . $this->getUserToken()
                )
            );
        }

        $data = $this->model_extension_alliance_payment_data->fillSettingData($this->session, $this->config, $this->request);

        $this->response->setOutput($this->load->view('extension/alliance/payment/alliance', $data));
    }

    public function install(): void
    {
        $this->load->language('extension/alliance/payment/alliance');
        $this->load->model('localisation/order_status');
        $this->load->model('setting/setting');
        $this->load->model('extension/alliance/payment/alliance');
        $this->load->model('extension/alliance/payment/db');
        $this->load->model('user/user_group');

        $default_pending = 1;
        $default_paid = 7;
        $default_error = 10;
        $catalog_url = !empty($this->config->get('config_url')) ? $this->config->get('config_url') : HTTP_CATALOG;

        $default_settings = [
            'payment_alliance_paid_status' => $default_paid,
            'payment_alliance_pending_status' => $default_pending,
            'payment_alliance_error_status' => $default_error,
            'payment_alliance_return_url' => '',
            'payment_alliance_success_url' => $catalog_url . 'index.php?route=checkout/success',
            'payment_alliance_fail_url' => $catalog_url . 'index.php?route=checkout/failure',
            'payment_alliance_debug' => '0',
            'payment_alliance_version' => '0.0.5',
            'payment_alliance_preauth_status' => $default_pending,
            'payment_alliance_completion_status' => $default_paid,
            'payment_alliance_preauth_exp_date' => '2h',
        ];

        $this->model_user_user_group->addPermission(
            $this->user->getGroupId(),
            'access',
            'extension/alliance/alliance/refund'
        );

        $this->model_user_user_group->addPermission(
            $this->user->getGroupId(),
            'modify',
            'extension/alliance/alliance/refund'
        );

        $this->model_setting_setting->editSetting('payment_alliance', $default_settings);
        $this->model_extension_alliance_payment_db->initDB();
        $this->installEvents();
    }

    public function uninstall(): void
    {
        $this->load->language('extension/alliance/payment/alliance');
        $this->load->model('extension/alliance/payment/alliance');
        $this->load->model('setting/event');
        $this->load->model('user/user_group');

        $json = [];

        if (!$this->user->hasPermission('modify', 'extension/alliance/payment/alliance')) {
            $json['error'] = $this->language->get('error_permission');
        }

        if (!$json) {
            foreach ($this->events as $event) {
                $this->model_setting_event->deleteEventByCode($event['event_code']);
            }

            $this->model_user_user_group->removePermission(
                $this->user->getGroupId(),
                'access',
                'extension/alliance/alliance/refund'
            );

            $this->model_user_user_group->removePermission(
                $this->user->getGroupId(),
                'modify',
                'extension/alliance/alliance/refund'
            );

            $json['success'] = $this->language->get('text_success');
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    private function installEvents(): void
    {
        $this->load->model('setting/event');

        $defaults = [
            'status' => 1,
            'sort_order' => 0,
            'description' => '',
        ];

        foreach ($this->events as $event) {
            $this->model_setting_event->deleteEventByCode($event['event_code']);
            $event['code'] = $event['event_code'];
            // add default keys in array
            foreach ($defaults as $key => $value) {
                if (!isset($event[$key])) {
                    $event[$key] = $value;
                }
            }

            if (VERSION > '4.0.1.1') {
                $event['trigger'] = str_replace('|', '.', $event['trigger']);
                $event['action'] = str_replace('|', '.', $event['action']);
            }

            $this->model_setting_event->addEvent([
                'code' => $event['event_code'],
                'trigger' => $event['trigger'],
                'action' => $event['action'],
                'description' => $event['description'],
                'status' => $event['status'],
                'sort_order' => $event['sort_order'],
            ]);
        }
    }

    public function operationsEvent(&$route, &$data, &$code): void
    {
        $this->load->language('extension/alliance/payment/alliance');
        $this->load->model('extension/alliance/payment/alliance');
        $this->load->model('sale/order');
        $this->load->helper('extension/alliance/alliance/config');
        $this->load->helper('extension/alliance/alliance/ecom');
        $alliance_order = $this->model_extension_alliance_payment_alliance->getAllianceOrder($data['order_id']);
        $order_info = $this->model_sale_order->getOrder($data['order_id']);

        if ($order_info['payment_method']['code'] === Config::ALLIANCE_PAYMENT_CODE
            && !empty($alliance_order)
        ) {
            $data['text_form'] .= ' ' . $this->getCheckStatusButtonHtml($data);
        }
    }

    public function injectCompletionButton(string &$route, array &$data, mixed &$output)
    {
        $this->load->language('extension/alliance/payment/alliance');
        $this->load->model('extension/alliance/payment/alliance');
        $this->load->helper('extension/alliance/alliance/ecom');
        $alliance_order = $this->model_extension_alliance_payment_alliance->getAllianceOrder($data['order_id']);

        if (($alliance_order['hpp_pay_type'] ?? '') === Ecom::HPP_PAY_TYPE_PREAUTH) {
            $output = str_replace('</body>', $this->getConfirmPaymentButtonHtml((int)$data['order_id']) . '</body>', $output);
        }
    }

    public function upgrade()
    {
        $this->load->model('extension/alliance/payment/db');
        $this->model_extension_alliance_payment_db->updateAllianceOrderTable();
        $this->load->model('setting/setting');
        $this->model_setting_setting->editSetting('payment_alliance', ['payment_alliance_version' => '1.0.0']);
    }

    public function checkOperationStatus(): bool
    {
        $this->load->language('extension/alliance/payment/alliance');
        $this->document->setTitle($this->language->get('heading_title_operations'));

        $this->load->model('extension/alliance/payment/alliance');
        $this->load->helper('extension/alliance/alliance/ecom');
        $this->load->model('extension/alliance/payment/refund');

        $referer = $this->request->server['HTTP_REFERER'] ?? $this->url->link('common/dashboard');
        $order_id = $this->request->get['order_id'] ?? 0;

        $data['heading_title'] = $this->language->get('heading_title_operations');
        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');
        $data['back'] = $referer;

        $alliance_order = $this->model_extension_alliance_payment_alliance->getAllianceOrder($order_id);
        $order_callback = !empty($alliance_order['callback_data'])
            ? json_decode($alliance_order['callback_data'], true) : [];

        if (!empty($alliance_order) && !empty($order_callback)) {
            $this->response->setOutput($this->load->view('extension/alliance/payment/operation_info',
                $this->model_extension_alliance_payment_alliance->fillOperationStatusData($data, $order_callback, false,
                    $this->model_extension_alliance_payment_refund->checkIfOrderRefunded($order_id),
                    $alliance_order)));
            return true;
        }

        try {
            $ecom = new Ecom($this->registry);
            $jwe_token = $ecom->authorizeByVirtualDevice($this->config->get('payment_alliance_service_code_id'));
            $decrypt_auth = $ecom->decryptResponse($jwe_token);
            $e_com_response = $ecom->checkOperationStatus($decrypt_auth, $alliance_order['hpp_order_id']);
        } catch (\Exception $exception) {
            $json['msgType'] = $this->language->get('error_technical');
            $this->response->setOutput($this->load->view('extension/alliance/payment/operation_info',
                $this->model_extension_alliance_payment_alliance->fillOperationStatusData($data, $json, true, false, $alliance_order)));
            return false;
        }

        $this->response->setOutput(
            $this->load->view('extension/alliance/payment/operation_info',
                $this->model_extension_alliance_payment_alliance->fillOperationStatusData(
                    $data,
                    $e_com_response,
                    false,
                    $this->model_extension_alliance_payment_refund->checkIfOrderRefunded($order_id),
                    $alliance_order
                )
            )
        );

        return true;
    }

    /**
     * @return void
     */
    public function executePreAuthCompletion(): void
    {
        $this->load->language('extension/alliance/payment/alliance');
        $this->load->model('extension/alliance/payment/alliance');
        $this->load->model('sale/order');
        $this->load->helper('extension/alliance/alliance/ecom');
        $this->load->helper('extension/alliance/alliance/config');

        $json = [];
        $order_id = (int)($this->request->get['order_id'] ?? 0);

        if (!$order_id) {
            $json['success'] = false;
            $json['message'] = $this->language->get('error_something_went_wrong');
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($json));
            return;
        }

        $alliance_order = $this->model_extension_alliance_payment_alliance->getAllianceOrder($order_id);

        if (empty($alliance_order) || ($alliance_order['hpp_pay_type'] ?? '') !== Ecom::HPP_PAY_TYPE_PREAUTH) {
            $json['success'] = false;
            $json['message'] = $this->language->get('error_not_preauth_order');
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($json));
            return;
        }

        $order = $this->model_sale_order->getOrder($order_id);

        $conversionRate = (float)($alliance_order['conversion_rate'] ?? 0);
        if ($conversionRate > 0) {
            $current_coin_amount = (int)round((float)$order['total'] * $conversionRate * 100);
        } else {
            $current_coin_amount = (int)round((float)$order['total'] * 100);
        }

        $original_amount = (int)($alliance_order['original_authorized_amount'] * $conversionRate ?? 0);

        if ($original_amount === 0) {
            $json['success'] = false;
            $json['message'] = $this->language->get('error_completion_amount');
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($json));
            return;
        }

        $min_allowed = (int)floor($original_amount * (1 - Config::PREAUTH_AMOUNT_TOLERANCE));
        $max_allowed = (int)ceil($original_amount * (1 + Config::PREAUTH_AMOUNT_TOLERANCE));

        if ($current_coin_amount < $min_allowed || $current_coin_amount > $max_allowed) {
            $json['success'] = false;
            $json['message'] = $this->language->get('error_completion_amount');
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($json));
            return;
        }

        try {
            $ecom = new Ecom($this->registry);
            $jwe_token = $ecom->authorizeByVirtualDevice($this->config->get('payment_alliance_service_code_id'));
            $decrypt_auth = $ecom->decryptResponse($jwe_token);

            $completion_data = [
                'merchant_id' => $alliance_order['merchant_id'],
                'operation_id' => $alliance_order['operation_id'],
                'coin_amount' => (string)$current_coin_amount,
            ];

            $result = $ecom->proceedCompletion($decrypt_auth, $completion_data);

            if (!empty($result['msgType']) && $result['msgType'] === 'ERROR') {
                throw new \Exception(
                    $this->language->get('error_completion_failed') . ': ' . ($result['msgText'] ?? '')
                );
            }

            $json['success'] = true;
            $json['message'] = $this->language->get('text_completion_success');
        } catch (\Throwable $e) {
            $this->log->write('[Alliance PREAUTH completion] order_id=' . $order_id . ' ' . $e->getMessage());
            $json['success'] = false;
            $json['message'] = $this->language->get('error_something_went_wrong');
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * @return string
     */
    private function getUserToken(): string
    {
        $session = $this->session;

        return $session->data['user_token'];
    }

    /**
     * @param array $data
     * @return string
     */
    private function getCheckStatusButtonHtml(array $data): string
    {
        $this->load->language('extension/alliance/payment/alliance');

        return "<a class='btn btn-primary' style='float:right' "
            . "href=index.php?route=extension/alliance/payment/alliance|checkOperationStatus&user_token="
            . $this->getUserToken()
            . "&order_id="
            . $data['order_id']
            . ">" . $this->language->get('button_check_status') . "</a>";
    }

    /**
     * @param int $order_id
     * @return string
     */
    private function getConfirmPaymentButtonHtml(int $order_id): string
    {
        $completion_url = $this->url->link(
            'extension/alliance/payment/alliance|executePreAuthCompletion',
            'user_token=' . $this->getUserToken() . '&order_id=' . $order_id,
            true
        );

        $button_label   = json_encode($this->language->get('button_confirm_payment'), JSON_UNESCAPED_UNICODE);
        $error_fallback = json_encode($this->language->get('error_something_went_wrong'), JSON_UNESCAPED_UNICODE);

        return <<<HTML
        <script>
        (function () {
            document.addEventListener('DOMContentLoaded', function () {
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'btn btn-warning';
                btn.style.cssText = 'margin-right:5px;';
                btn.innerHTML = {$button_label};
                btn.onclick = function () {
                    btn.disabled = true;
                    fetch('{$completion_url}')
                        .then(function (r) { return r.json(); })
                        .then(function (data) {
                            if (data.success) {
                                alert(data.message);
                            } else {
                                alert(data.message || {$error_fallback});
                                btn.disabled = false;
                            }
                            location.reload();
                        })
                        .catch(function () {
                            btn.disabled = false;
                        });
                };
                var target = document.querySelector('#button-partial-return');
                if (target) {
                    target.after(btn);
                }
            });
        })();
        </script>
        HTML;
    }
}
