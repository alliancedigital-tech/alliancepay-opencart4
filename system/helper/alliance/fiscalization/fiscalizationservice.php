<?php

declare(strict_types=1);

namespace Opencart\System\Helper\Alliance\Fiscalization;

require_once __DIR__ . '/fiscalizationrepository.php';
require_once __DIR__ . '/fiscalizationproductsbuilder.php';

use Opencart\System\Engine\Registry;
use Opencart\System\Helper\Alliance\Ecom;
use Opencart\System\Helper\Alliance\log\AllianceLogger;
use Opencart\System\Helper\Alliance\RequestIdentification\GenerateRequestIdentification;
use Psr\Log\LogLevel;
use Throwable;

class FiscalizationService
{
    public const TYPE_PURCHASE = 'PURCHASE';
    public const TYPE_REFUND = 'REFUND';

    private const BUSINESS_ERROR_CODES = [
        'missing_required_field', 'invalid_format', 'payment_not_found', 'original_receipt_not_found',
    ];

    private const STATUS_RANK = [
        FiscalizationRepository::STATUS_ERROR => 0,
        FiscalizationRepository::STATUS_PENDING => 1,
        FiscalizationRepository::STATUS_FAIL => 2,
        FiscalizationRepository::STATUS_SUCCESS => 3,
    ];

    private const SENSITIVE_KEYS = ['private_key', 'jws', 'signature', 'authorization'];

    private Registry $registry;
    private object $config;
    private object $db;
    private object $language;
    private FiscalizationRepository $repository;
    private AllianceLogger $logger;
    private ?Ecom $ecom = null;

    public function __construct(Registry $registry)
    {
        $this->registry = $registry;
        $this->config = $registry->get('config');
        $this->db = $registry->get('db');
        $this->language = $registry->get('language');
        $registry->get('load')->helper('extension/alliance/alliance/log/alliancelogger');
        $this->repository = new FiscalizationRepository($registry);
        $this->logger = new AllianceLogger($registry);
    }

    public function isEnabled(): bool
    {
        return (bool)$this->config->get('payment_alliance_fiscalization_enabled');
    }

    public function isAutoEnabled(): bool
    {
        return $this->isEnabled() && $this->config->get('payment_alliance_fiscalization_auto');
    }

    public function autoFiscalizePurchase(int $orderId): void
    {
        if (!$this->isAutoEnabled()) {
            return;
        }
        $this->fiscalizePurchase($orderId);
    }

    public function autoFiscalizeRefund(int $orderId, string $refundOperationId): void
    {
        if (!$this->isAutoEnabled()) {
            return;
        }
        $this->fiscalizeRefund($orderId, $refundOperationId);
    }

    public function fiscalizePurchase(int $orderId): array
    {
        try {
            $allianceOrder = $this->getAllianceOrder($orderId);
            $operationId = trim((string)($allianceOrder['operation_id'] ?? ''));
            if ($operationId === '') {
                return $this->result(false, '', $this->t('error_fiscalization_no_operation'));
            }

            $coin = (int)$allianceOrder['coin_amount'];
            $rate = (float)($allianceOrder['conversion_rate'] ?? 0);
            if ($rate > 0) {
                $coin = (int)round($coin * $rate);
            }

            return $this->process(
                $orderId,
                $operationId,
                self::TYPE_PURCHASE,
                $coin,
                ['products' => null, 'shipping' => true],
                'PAYMENT_FOR_ORDER_' . $orderId
            );
        } catch (Throwable $e) {
            return $this->failure($e);
        }
    }

    public function fiscalizeRefund(int $orderId, string $refundOperationId): array
    {
        try {
            $query = $this->db->query(
                "SELECT * FROM `" . DB_PREFIX . "alliance_integration_order_refund` WHERE `order_id` = '" . $orderId
                . "' AND `operation_id` = '" . $this->db->escape($refundOperationId) . "' LIMIT 1"
            );
            if (empty($query->row)) {
                return $this->result(false, '', $this->t('error_fiscalization_no_operation'));
            }

            $items = !empty($query->row['refund_items']) ? json_decode($query->row['refund_items'], true) : null;

            return $this->process(
                $orderId,
                $refundOperationId,
                self::TYPE_REFUND,
                (int)$query->row['coin_amount'],
                is_array($items) ? $items : ['products' => null, 'shipping' => true],
                'REFUND_FOR_ORDER_' . $orderId
            );
        } catch (Throwable $e) {
            return $this->failure($e);
        }
    }

    public function refresh(string $operationId): array
    {
        try {
            $row = $this->repository->findByOperationId($operationId);
            if (!$row || empty($row['request_payload'])) {
                return $this->result(false, '', $this->t('error_fiscalization_no_operation'));
            }

            return $this->send($row, json_decode($row['request_payload'], true));
        } catch (Throwable $e) {
            return $this->failure($e);
        }
    }

    public function handleCallback(array $payload): int
    {
        try {
            $this->logger->log(['fiscalization callback' => $this->mask($payload)]);

            $row = null;
            if (!empty($payload['operationId'])) {
                $row = $this->repository->findByOperationId((string)$payload['operationId']);
            }
            if (!$row && !empty($payload['merchantRequestId'])) {
                $row = $this->repository->findByMerchantRequestId((string)$payload['merchantRequestId']);
            }
            if (!$row) {
                $this->logger->log('Fiscalization callback: record not found', LogLevel::WARNING);

                return 404;
            }

            $this->applyData($row, $payload, 'callback_payload');

            return 200;
        } catch (Throwable $e) {
            $this->logger->log('Fiscalization callback error: ' . $e->getMessage(), LogLevel::ERROR);

            return 500;
        }
    }

    public static function canFiscalize(?array $row): bool
    {
        if (!$row) {
            return true;
        }

        return $row['status'] === FiscalizationRepository::STATUS_ERROR
            && !in_array($row['error_code'], self::BUSINESS_ERROR_CODES, true);
    }

    private function process(
        int $orderId,
        string $operationId,
        string $type,
        int $targetKopecks,
        array $items,
        string $comment
    ): array {
        if (!$this->isEnabled()) {
            return $this->result(false, '', $this->t('error_fiscalization_disabled'));
        }

        $existing = $this->repository->findByOperationId($operationId);
        if ($existing && !self::canFiscalize($existing)) {
            return $this->result(false, $existing['status'], $this->t('error_fiscalization_already'));
        }

        $ecom = $this->getEcom();
        $base = [
            'order_id' => $orderId,
            'store_id' => $this->getStoreId($orderId),
            'operation_id' => $operationId,
            'operation_type' => $type,
            'merchant_request_id' => GenerateRequestIdentification::generateRequestId(),
            'receipt_comment' => $comment,
        ];

        $built = FiscalizationProductsBuilder::build(
            $this->getLines($orderId, $items),
            $targetKopecks,
            FiscalizationValueResolver::defaultsFromConfig($this->config)
        );

        if ($built['errors']) {
            $message = $this->t('error_fiscalization_validation', implode('; ', $this->describeErrors($built['errors'])));
            $this->repository->upsert($base + [
                'status' => FiscalizationRepository::STATUS_ERROR,
                'error_code' => 'validation',
                'error_description' => $message,
            ]);
            $this->logger->log($message, LogLevel::WARNING);

            return $this->result(false, FiscalizationRepository::STATUS_ERROR, $message);
        }

        $request = [
            'merchantRequestId' => $base['merchant_request_id'],
            'merchantId' => (string)$this->config->get('payment_alliance_merchant_id'),
            'operationId' => $operationId,
            'receiptComment' => $comment,
            'notificationUrl' => $ecom->getFiscalizationNotificationUrl(),
            'products' => $built['products'],
        ];

        $row = $this->repository->upsert($base + [
            'status' => FiscalizationRepository::STATUS_ERROR,
            'error_code' => null,
            'error_description' => null,
            'request_payload' => json_encode($request, JSON_UNESCAPED_UNICODE),
        ]);

        return $this->send($row, $request);
    }

    private function send(array $row, array $request): array
    {
        $this->logger->log(['fiscalization request' => $this->mask($request)]);

        try {
            $response = $this->getEcom()->createFiscalization($request);
        } catch (Throwable $e) {
            return $this->storeError($row, 'SIGN', $e->getMessage());
        }

        $this->logger->log(['fiscalization response' => $this->mask($response)]);

        if (!$response['ok']) {
            $data = $response['data'];
            $code = (string)($data['msgCode'] ?? ($response['http_status'] ? 'HTTP_' . $response['http_status'] : 'NETWORK'));
            $attrs = $data['msgAttrs'] ?? '';
            $attrs = is_array($attrs) ? implode(', ', array_map('strval', $attrs)) : (string)$attrs;

            $known = [
                'missing_required_field' => 'error_fiscalization_missing_required_field',
                'invalid_format' => 'error_fiscalization_invalid_format',
                'payment_not_found' => 'error_fiscalization_payment_not_found',
                'original_receipt_not_found' => 'error_fiscalization_original_receipt_not_found',
            ];
            $message = isset($known[$code])
                ? $this->t($known[$code], $attrs)
                : $this->t('error_fiscalization_request', $response['error']);

            return $this->storeError($row, $code, $message);
        }

        $updated = $this->applyData($row, $response['data'], 'response_payload');
        $status = $updated['status'] ?? FiscalizationRepository::STATUS_ERROR;
        $success = in_array($status, [FiscalizationRepository::STATUS_PENDING, FiscalizationRepository::STATUS_SUCCESS], true);

        return $this->result(
            $success,
            $status,
            $success ? $this->t('text_fiscalization_sent', $status) : (string)($updated['error_description'] ?? '')
        );
    }

    private function applyData(array $row, array $data, string $payloadColumn): array
    {
        $status = strtoupper((string)($data['status'] ?? ''));
        if (!isset(self::STATUS_RANK[$status])) {
            $status = $row['status'];
        }

        $modification = $this->parseDate($data['fiscalizationModificationDateTime'] ?? ($data['modificationDateTime'] ?? null));
        $stale = $modification !== null
            && !empty($row['fiscalization_modification_date_time'])
            && $modification < $row['fiscalization_modification_date_time'];
        $regress = self::STATUS_RANK[$status] < self::STATUS_RANK[$row['status']]
            && $row['status'] !== FiscalizationRepository::STATUS_ERROR;

        $update = ['operation_id' => $row['operation_id'], $payloadColumn => json_encode($data, JSON_UNESCAPED_UNICODE)];

        if (!$stale && !$regress) {
            $update += [
                'status' => $status,
                'bucket_id' => $data['bucketId'] ?? $row['bucket_id'],
                'fiscal_number' => $data['fiscalNumber'] ?? $row['fiscal_number'],
                'receipt_url' => $data['receiptUrl'] ?? $row['receipt_url'],
                'fiscalization_date_time' => $this->parseDate($data['fiscalizationDateTime'] ?? null)
                    ?? $row['fiscalization_date_time'],
                'fiscalization_modification_date_time' => $modification ?? $row['fiscalization_modification_date_time'],
                'error_code' => isset($data['errorCode']) ? (string)$data['errorCode'] : null,
                'error_description' => $data['errorDescription'] ?? null,
            ];
        }

        $saved = $this->repository->upsert($update);

        if (!$stale && !$regress && $status !== $row['status']) {
            if ($status === FiscalizationRepository::STATUS_SUCCESS) {
                $this->addOrderComment((int)$row['order_id'], $this->t('comment_fiscalization_success', (string)$saved['fiscal_number']));
            } elseif ($status === FiscalizationRepository::STATUS_FAIL) {
                $this->addOrderComment(
                    (int)$row['order_id'],
                    $this->t('comment_fiscalization_fail', (string)$saved['error_code'], (string)$saved['error_description'])
                );
            }
        }

        return $saved;
    }

    private function storeError(array $row, string $code, string $message): array
    {
        $this->logger->log('Fiscalization error ' . $code . ': ' . $message, LogLevel::ERROR);
        $this->repository->upsert([
            'operation_id' => $row['operation_id'],
            'status' => FiscalizationRepository::STATUS_ERROR,
            'error_code' => $code,
            'error_description' => $message,
        ]);

        return $this->result(false, FiscalizationRepository::STATUS_ERROR, $message);
    }

    private function getLines(int $orderId, array $items): array
    {
        $selected = isset($items['products']) && is_array($items['products'])
            ? array_map('intval', $items['products'])
            : null;
        $lines = [];

        $products = $this->db->query(
            "SELECT * FROM `" . DB_PREFIX . "order_product` WHERE `order_id` = '" . $orderId . "'"
        )->rows;
        foreach ($products as $product) {
            if ($selected !== null && !in_array((int)$product['order_product_id'], $selected, true)) {
                continue;
            }
            $attributes = $this->repository->getProductAttributes((int)$product['product_id']);
            $lines[] = [
                'id' => (string)$product['product_id'],
                'name' => (string)$product['name'],
                'quantity' => (float)$product['quantity'],
                'weight' => ((float)$product['price'] + (float)$product['tax']) * (float)$product['quantity'],
                'attributes' => $attributes,
            ];
        }

        if (!empty($items['shipping'])) {
            $totals = $this->db->query(
                "SELECT * FROM `" . DB_PREFIX . "order_total` WHERE `order_id` = '" . $orderId . "'"
            )->rows;
            foreach ($totals as $total) {
                if (in_array($total['code'], ['sub_total', 'total', 'tax', 'coupon', 'reward', 'voucher', 'credit'], true)
                    || (float)$total['value'] <= 0
                ) {
                    continue;
                }
                $lines[] = [
                    'id' => (string)$total['code'],
                    'name' => (string)$total['title'],
                    'quantity' => 1,
                    'weight' => (float)$total['value'],
                    'attributes' => [],
                ];
            }
        }

        return $lines;
    }

    private function describeErrors(array $errors): array
    {
        $messages = [];
        foreach ($errors as $error) {
            $text = [
                'measurement_required' => $this->t('error_fiscalization_measurement_code'),
                'classification_required' => $this->t('error_fiscalization_classification_required'),
                'no_products' => 'products',
                'invalid_amount' => 'amount',
            ][$error['code']] ?? $error['code'];
            $messages[] = trim($error['name'] . ': ' . $text, ': ');
        }

        return $messages;
    }

    private function getAllianceOrder(int $orderId): array
    {
        return $this->db->query(
            "SELECT * FROM `" . DB_PREFIX . "alliance_checkout_integration_order` WHERE `order_id` = '" . $orderId . "'"
        )->row;
    }

    private function getStoreId(int $orderId): int
    {
        return (int)($this->db->query(
            "SELECT `store_id` FROM `" . DB_PREFIX . "order` WHERE `order_id` = '" . $orderId . "'"
        )->row['store_id'] ?? 0);
    }

    private function addOrderComment(int $orderId, string $comment): void
    {
        $order = $this->db->query(
            "SELECT `order_status_id` FROM `" . DB_PREFIX . "order` WHERE `order_id` = '" . $orderId . "'"
        )->row;
        if (!$order) {
            return;
        }

        $this->db->query(
            "INSERT INTO `" . DB_PREFIX . "order_history` SET `order_id` = '" . $orderId . "', `order_status_id` = '"
            . (int)$order['order_status_id'] . "', `notify` = '0', `comment` = '" . $this->db->escape($comment)
            . "', `date_added` = NOW()"
        );
    }

    private function parseDate(?string $value): ?string
    {
        if (empty($value)) {
            return null;
        }
        try {
            $date = new \DateTime($value);
            $date->setTimezone(new \DateTimeZone(date_default_timezone_get()));

            return $date->format('Y-m-d H:i:s');
        } catch (Throwable $e) {
            return null;
        }
    }

    private function getEcom(): Ecom
    {
        if ($this->ecom === null) {
            $this->registry->get('load')->helper('extension/alliance/alliance/ecom');
            $this->ecom = new Ecom($this->registry);
        }

        return $this->ecom;
    }

    private function mask($value)
    {
        if (!is_array($value)) {
            return $value;
        }
        $masked = [];
        foreach ($value as $key => $item) {
            $masked[$key] = is_string($key) && in_array(strtolower($key), self::SENSITIVE_KEYS, true)
                ? '***'
                : $this->mask($item);
        }

        return $masked;
    }

    private function t(string $key, string ...$args): string
    {
        $text = (string)$this->language->get($key);

        return $args ? vsprintf($text, $args) : $text;
    }

    private function result(bool $success, string $status, string $message): array
    {
        return ['success' => $success, 'status' => $status, 'message' => $message];
    }

    private function failure(Throwable $e): array
    {
        $this->logger->log('Fiscalization failed: ' . $e->getMessage(), LogLevel::ERROR);

        return $this->result(false, FiscalizationRepository::STATUS_ERROR, $this->t('error_fiscalization_request', $e->getMessage()));
    }
}
