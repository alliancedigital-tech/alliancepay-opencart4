<?php

declare(strict_types=1);

namespace Opencart\Admin\Controller\Extension\Alliance\Alliance;

use Opencart\System\Engine\Controller;
use Opencart\System\Engine\Registry;
use Opencart\System\Helper\Alliance\Config;
use Opencart\System\Helper\Alliance\Fiscalization\FiscalizationRepository;
use Opencart\System\Helper\Alliance\Fiscalization\FiscalizationService;
use Opencart\System\Helper\Alliance\Fiscalization\FiscalizationValidator;

class Fiscalization extends Controller
{
    private const ROUTE = 'extension/alliance/alliance/fiscalization';

    public function __construct(Registry $registry)
    {
        parent::__construct($registry);
        $this->load->language('extension/alliance/payment/alliance');
        $this->load->helper('extension/alliance/alliance/config');
        $this->load->helper('extension/alliance/alliance/fiscalization/fiscalizationservice');
    }

    public function injectOrderBlock(string &$route, array &$args, mixed &$output): void
    {
        $orderId = (int)($args['order_id'] ?? ($this->request->get['order_id'] ?? 0));
        if (!$orderId) {
            return;
        }

        $this->load->model('sale/order');
        $order = $this->model_sale_order->getOrder($orderId);
        $allianceOrder = $this->db->query(
            "SELECT `operation_id` FROM `" . DB_PREFIX . "alliance_checkout_integration_order` WHERE `order_id` = '"
            . $orderId . "'"
        )->row;

        if (empty($order['payment_method']['code'])
            || $order['payment_method']['code'] !== Config::ALLIANCE_PAYMENT_CODE
            || empty($allianceOrder)
        ) {
            return;
        }

        $repository = new FiscalizationRepository($this->registry);
        $records = [];
        foreach ($repository->findByOrderId($orderId) as $record) {
            $records[$record['operation_id']] = $record;
        }

        $enabled = (bool)$this->config->get('payment_alliance_fiscalization_enabled');
        $canModify = $enabled && $this->user->hasPermission('modify', self::ROUTE);

        $operations = [];
        if (!empty($allianceOrder['operation_id'])) {
            $operations[] = ['id' => $allianceOrder['operation_id'], 'type' => FiscalizationService::TYPE_PURCHASE];
        }
        $refunds = $this->db->query(
            "SELECT `operation_id`, `status` FROM `" . DB_PREFIX . "alliance_integration_order_refund` WHERE `order_id` = '"
            . $orderId . "'"
        )->rows;
        foreach ($refunds as $refund) {
            if (strtoupper((string)$refund['status']) === FiscalizationRepository::STATUS_SUCCESS && $refund['operation_id'] !== '') {
                $operations[] = ['id' => $refund['operation_id'], 'type' => FiscalizationService::TYPE_REFUND];
            }
        }

        if (!$records && !$enabled) {
            return;
        }

        $token = $this->session->data['user_token'];
        $rows = '';
        foreach ($operations as $operation) {
            $record = $records[$operation['id']] ?? null;
            $actions = '';

            if ($canModify && FiscalizationService::canFiscalize($record)) {
                $actions .= $this->button('send', $operation['type'], $operation['id'], $orderId, $token, 'button_fiscalize', 'btn-primary');
            }
            if ($canModify && $record && $record['status'] !== FiscalizationRepository::STATUS_ERROR) {
                $actions .= $this->button('refresh', $operation['type'], $operation['id'], $orderId, $token, 'button_fiscalization_refresh', 'btn-light');
            }

            $receipt = !empty($record['receipt_url'])
                ? '<a href="' . $this->e($record['receipt_url']) . '" target="_blank" rel="noopener">'
                    . $this->e($this->language->get('column_fiscalization_receipt')) . '</a>'
                : '';
            $error = $record ? trim(($record['error_code'] ?? '') . ' ' . ($record['error_description'] ?? '')) : '';

            $rows .= '<tr><td>' . $this->e($operation['type']) . '</td>'
                . '<td>' . $this->e($record['status'] ?? '—') . '</td>'
                . '<td>' . $this->e($record['fiscal_number'] ?? '') . '</td>'
                . '<td>' . $receipt . '</td>'
                . '<td>' . $this->e($record['fiscalization_date_time'] ?? '') . '</td>'
                . '<td>' . $this->e($error) . '</td>'
                . '<td class="text-end">' . $actions . '</td></tr>';
        }

        if ($rows === '') {
            $rows = '<tr><td colspan="7">' . $this->e($this->language->get('text_fiscalization_none')) . '</td></tr>';
        }

        $html = '<div id="alliance-fiscalization" class="card mb-3"><div class="card-header"><i class="fa-solid fa-receipt"></i> '
            . $this->e($this->language->get('text_fiscalization_block')) . '</div><div class="table-responsive">'
            . '<table class="table table-bordered mb-0"><thead><tr>'
            . '<td>' . $this->e($this->language->get('column_fiscalization_type')) . '</td>'
            . '<td>' . $this->e($this->language->get('column_fiscalization_status')) . '</td>'
            . '<td>' . $this->e($this->language->get('column_fiscalization_number')) . '</td>'
            . '<td>' . $this->e($this->language->get('column_fiscalization_receipt')) . '</td>'
            . '<td>' . $this->e($this->language->get('column_fiscalization_date')) . '</td>'
            . '<td>' . $this->e($this->language->get('column_fiscalization_error')) . '</td>'
            . '<td></td></tr></thead><tbody>' . $rows . '</tbody></table></div></div>';

        $script = '<script>document.addEventListener("DOMContentLoaded", function () {'
            . 'var host = document.querySelector("#content .container-fluid:last-of-type");'
            . 'if (!host) { return; }'
            . 'var wrap = document.createElement("div"); wrap.innerHTML = ' . json_encode($html, JSON_UNESCAPED_UNICODE) . ';'
            . 'host.appendChild(wrap.firstChild);'
            . 'document.querySelectorAll(".alliance-fiscalization-action").forEach(function (btn) {'
            . 'btn.addEventListener("click", function () {'
            . 'btn.disabled = true;'
            . 'fetch(btn.getAttribute("data-url")).then(function (r) { return r.json(); })'
            . '.then(function (res) { alert(res.message || ""); location.reload(); })'
            . '.catch(function (e) { btn.disabled = false; alert(e.message); });'
            . '});});});</script>';

        $output = str_replace('</body>', $script . '</body>', $output);
    }

    public function send(): void
    {
        $json = $this->guard();

        if (!$json) {
            $orderId = (int)($this->request->get['order_id'] ?? 0);
            $service = new FiscalizationService($this->registry);
            $result = ($this->request->get['type'] ?? '') === FiscalizationService::TYPE_REFUND
                ? $service->fiscalizeRefund($orderId, (string)($this->request->get['operation_id'] ?? ''))
                : $service->fiscalizePurchase($orderId);
            $json = ['success' => $result['success'], 'message' => $result['message']];
        }

        $this->respond($json);
    }

    public function refresh(): void
    {
        $json = $this->guard();

        if (!$json) {
            $operationId = (string)($this->request->get['operation_id'] ?? '');
            $record = (new FiscalizationRepository($this->registry))->findByOperationId($operationId);

            if (!$record || (int)$record['order_id'] !== (int)($this->request->get['order_id'] ?? 0)) {
                $json = ['success' => false, 'message' => $this->language->get('error_fiscalization_no_operation')];
            } else {
                $result = (new FiscalizationService($this->registry))->refresh($operationId);
                $json = ['success' => $result['success'], 'message' => $result['message']];
            }
        }

        $this->respond($json);
    }

    public function injectProductTab(string &$route, array &$args, mixed &$output): void
    {
        $productId = (int)($args['product_id'] ?? ($this->request->get['product_id'] ?? 0));
        $values = $productId ? (new FiscalizationRepository($this->registry))->getProductAttributes($productId) : [];
        $posted = $this->request->post['alliance_fiscalization'] ?? [];

        $field = function (string $name, string $labelKey, string $helpKey, string $tag = 'input') use ($values, $posted): string {
            $value = (string)($posted[$name] ?? $values[$name] ?? '');
            $id = 'input-alliance-fiscalization-' . str_replace('_', '-', $name);
            $control = $tag === 'textarea'
                ? '<textarea name="alliance_fiscalization[' . $name . ']" id="' . $id . '" rows="2" class="form-control">'
                    . $this->e($value) . '</textarea>'
                : '<input type="text" name="alliance_fiscalization[' . $name . ']" id="' . $id . '" value="'
                    . $this->e($value) . '" class="form-control"/>';

            return '<div class="row mb-3"><label for="' . $id . '" class="col-sm-2 col-form-label">'
                . $this->e($this->language->get($labelKey)) . '</label><div class="col-sm-10">' . $control
                . '<div class="form-text">' . $this->e($this->language->get($helpKey)) . '</div></div></div>';
        };

        $pane = '<div class="tab-pane" id="tab-alliance-fiscalization"><p class="text-muted">'
            . $this->e($this->language->get('text_fiscalization_product_help')) . '</p>'
            . $field('tax_code', 'entry_fiscalization_tax_code', 'help_fiscalization_tax_code')
            . $field('classification_code', 'entry_fiscalization_classification_code', 'help_fiscalization_classification_code')
            . $field('excise_labels', 'entry_fiscalization_excise_labels', 'help_fiscalization_excise_labels', 'textarea')
            . $field('measurement_code', 'entry_fiscalization_measurement_code', 'help_fiscalization_measurement_code')
            . '</div>';

        $script = '<script>document.addEventListener("DOMContentLoaded", function () {'
            . 'var form = document.getElementById("form-product"); if (!form) { return; }'
            . 'var tabs = form.querySelector(".nav-tabs"); var content = form.querySelector(".tab-content");'
            . 'if (!tabs || !content) { return; }'
            . 'var li = document.createElement("li"); li.className = "nav-item";'
            . 'li.innerHTML = \'<a href="#tab-alliance-fiscalization" data-bs-toggle="tab" class="nav-link">\' + '
            . json_encode($this->e($this->language->get('text_fiscalization_block')), JSON_UNESCAPED_UNICODE) . ' + \'</a>\';'
            . 'tabs.appendChild(li);'
            . 'var wrap = document.createElement("div"); wrap.innerHTML = ' . json_encode($pane, JSON_UNESCAPED_UNICODE) . ';'
            . 'content.appendChild(wrap.firstChild);'
            . '});</script>';

        $output = str_replace('</body>', $script . '</body>', $output);
    }

    public function saveProduct(string &$route, array &$args, mixed &$output): void
    {
        if (!isset($this->request->post['alliance_fiscalization']) || !is_array($this->request->post['alliance_fiscalization'])) {
            return;
        }

        $productId = str_contains($route, 'addProduct') ? (int)$output : (int)($args[0] ?? 0);
        if (!$productId) {
            return;
        }

        $this->load->helper('extension/alliance/alliance/fiscalization/fiscalizationvalidator');
        $posted = $this->request->post['alliance_fiscalization'];
        $values = [
            'tax_code' => trim((string)($posted['tax_code'] ?? '')),
            'classification_code' => trim((string)($posted['classification_code'] ?? '')),
            'excise_labels' => FiscalizationValidator::formatExciseLabels(
                FiscalizationValidator::parseExciseLabels($posted['excise_labels'] ?? '')
            ),
            'measurement_code' => trim((string)($posted['measurement_code'] ?? '')),
        ];

        foreach (array_keys(FiscalizationValidator::validate($values)) as $invalidField) {
            $values[$invalidField] = '';
        }

        (new FiscalizationRepository($this->registry))->saveProductAttributes($productId, $values);
    }

    public function deleteProduct(string &$route, array &$args, mixed &$output): void
    {
        if (!empty($args[0])) {
            (new FiscalizationRepository($this->registry))->deleteProductAttributes((int)$args[0]);
        }
    }

    private function guard(): array
    {
        if (!$this->user->hasPermission('modify', self::ROUTE)) {
            return ['success' => false, 'message' => $this->language->get('error_permission')];
        }
        if (!$this->config->get('payment_alliance_fiscalization_enabled')) {
            return ['success' => false, 'message' => $this->language->get('error_fiscalization_disabled')];
        }

        return [];
    }

    private function button(
        string $action,
        string $type,
        string $operationId,
        int $orderId,
        string $token,
        string $labelKey,
        string $class
    ): string {
        $url = 'index.php?route=' . self::ROUTE . '|' . $action . '&user_token=' . rawurlencode($token)
            . '&order_id=' . $orderId . '&type=' . rawurlencode($type) . '&operation_id=' . rawurlencode($operationId);

        return '<button type="button" class="btn btn-sm ' . $class . ' alliance-fiscalization-action ms-1" data-url="'
            . $this->e($url) . '">' . $this->e($this->language->get($labelKey)) . '</button>';
    }

    private function respond(array $json): void
    {
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json, JSON_UNESCAPED_UNICODE));
    }

    private function e($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}
