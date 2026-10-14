<?php

namespace Opencart\System\Helper\Alliance;

require_once __DIR__ . '/jws/jwsclienttrait.php';

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Opencart\System\Engine\Registry;
use Opencart\System\Helper\Alliance\Config;
use Opencart\System\Helper\Alliance\Jws\JwsClientTrait;
use Opencart\System\Helper\Alliance\log\AllianceLogger;
use Psr\Log\LogLevel;
use Throwable;

/**
 * Class Ecom.
 */
class Ecom
{
    use JwsClientTrait;

    const CREATE_ORDER = 'ecom/jws/payments/hpp/create_order_v1';

    const CREATE_REFUND = 'ecom/jws/payments/refund_v1';

    const COMPLETION = 'ecom/jws/payments/preauth/completion_v1';

    const CHECK_OPERATIONS = 'ecom/jws/payments/hpp/operations_v1';

    const GET_PUBLIC_KEY = 'ecom/keys/public_key/get_v1';

    const BANK_PUBLIC_KEY_CACHE_TTL = 3600;

    const METHOD_POST = 'POST';

    const NOTIFICATION_URL_ROUTE = 'extension/alliance/payment/alliance|notifyCallback';

    const HPP_PAY_TYPE_PURCHASE = 'PURCHASE';

    const HPP_PAY_TYPE_A2A = 'A2A';

    const HPP_PAY_TYPE_PREAUTH = 'PREAUTH';

    const CURRENCY_MAP = [
        'UAH' => 980,
        'USD' => 840,
        'EUR' => 978
    ];

    const A2A_DIRECT_TYPE = 'BANK_LINK';

    const A2A_PRIORITY_BANK_CODE = 'ALL_BANKS';

    const OPERATION_TYPE_PURCHASE = 'PURCHASE';

    const OPERATION_TYPE_A2A = 'ACCOUNT_2_ACCOUNT';

    const OPERATION_TYPE_REFUND = 'REFUND';

    const OPERATION_TYPE_PREAUTH = 'PREAUTH';
    const OPERATION_TYPE_COMPLETION = 'COMPLETION';

    private Datetime $dateTime;

    private Client $client;

    private ?object $config;

    private object $language;

    private $logger;

    private $cache;

    private AllianceCountryCodeProvider $countryCodeProvider;

    private $validationRules = [
        'senderCustomerId' => [
            'type' => 'string',
            'max_len' => 255,
            'required' => true,
        ],
        'senderFirstName' => [
            'type' => 'string',
            'max_len' => 30,
            'required' => false,
            'no_only_digits' => true,
            'pattern' => '/^[a-zA-Z0-9а-яА-ЯёЁіІїЇєЄґҐ]([a-zA-Z0-9а-яА-ЯёЁіІїЇєЄґҐ\s\-\']*[a-zA-Z0-9а-яА-ЯёЁіІїЇєЄґҐ])?$/u',
            'stop_words' => ['NULL', '3D SECURE', 'SURNAME', 'CARDHOLDER', 'UNKNOWN']
        ],
        'senderLastName' => [
            'type' => 'string',
            'max_len' => 30,
            'required' => false,
            'no_only_digits' => true,
            'pattern' => '/^[a-zA-Z0-9а-яА-ЯёЁіІїЇєЄґҐ]([a-zA-Z0-9а-яА-ЯёЁіІїЇєЄґҐ\s\-\']*[a-zA-Z0-9а-яА-ЯёЁіІїЇєЄґҐ])?$/u'
        ],
        'senderEmail' => [
            'type' => 'string',
            'max_len' => 256,
            'required' => false
        ],
        'senderCountry' => [
            'type' => 'string',
            'max_len' => 3,
            'required' => false
        ],
        'senderRegion' => [
            'type' => 'string',
            'max_len' => 255,
            'required' => false
        ],
        'senderCity' => [
            'type' => 'string',
            'max_len' => 25,
            'required' => false
        ],
        'senderStreet' => [
            'type' => 'string',
            'max_len' => 35,
            'required' => false
        ],
        'senderAdditionalAddress' => [
            'type' => 'string',
            'max_len' => 255,
            'required' => false
        ],
        'senderIp' => [
            'type' => 'string',
            'max_len' => 50,
            'required' => false
        ],
        'senderPhone' => [
            'type' => 'numeric_string',
            'max_len' => 20,
            'required' => false
        ],
        'senderZipCode' => [
            'type' => 'string',
            'max_len' => 50,
            'required' => false
        ],
    ];

    /**
     * @param string $code
     * @return int
     */
    public static function getCurrencyCode(string $code): int
    {
        if (!isset(self::CURRENCY_MAP[$code])) {
            throw new \InvalidArgumentException(
                'Unsupported currency code: '
                . $code
                . '. Supported currencies: '
                . implode(', ', array_keys(self::CURRENCY_MAP))
                . '.'
            );
        }

        return self::CURRENCY_MAP[$code];
    }

    /**
     * @param int $numericCode
     * @return string
     */
    public static function getCurrencyName(int $numericCode): string
    {
        static $reverseMap = null;

        if ($reverseMap === null) {
            $reverseMap = array_flip(self::CURRENCY_MAP);
        }

        return $reverseMap[$numericCode] ?? 'UAH';
    }

    public function __construct(Registry $registry)
    {
        $this->config = $registry->get('config');
        $this->language = $registry->get('language');
        $this->language->load('extension/alliance/payment/alliance');
        $loader = $registry->get('load');
        $loader->helper('extension/alliance/alliance/datetime');
        $loader->helper('extension/alliance/alliance/log/alliancelogger');
        $loader->helper('extension/alliance/alliance/alliancecountrycodeprovider');
        $loader->helper('extension/alliance/alliance/requestidentification/generaterequestidentification');
        $loader->helper('extension/alliance/alliance/config');
        $this->logger = new AllianceLogger($registry);
        $this->dateTime = new Datetime();
        $this->client = new Client(['base_uri' => $this->config->get('payment_alliance_url')]);
        $this->countryCodeProvider = new AllianceCountryCodeProvider();
        $this->cache = $registry->get('cache');

        $autoload = DIR_EXTENSION . 'alliance/system/library/vendor/autoload.php';
        $composerAutoloadByPath = DIR_EXTENSION . 'alliance/vendor/autoload.php';

        if (is_file($autoload)) {
            require_once($autoload);
        } elseif (is_file($composerAutoloadByPath)) {
            require_once($composerAutoloadByPath);
        } else {
            throw new \RuntimeException('Autoload file not found');
        }
    }

    /**
     * @param array $orderInfo
     * @param object $config
     * @param $url
     * @param $total_price
     * @return array
     * @throws Throwable
     */
    public function createCardHppOrder(array $orderInfo, object $config, $url, $total_price): array
    {
        try {
            $notificationUrl = $this->getNotificationUrl();
            $response = $this->client->request(self::METHOD_POST, self::CREATE_ORDER, [
                'headers' => $this->getJwsRequestHeaders(),
                'body' => $this->signJwsRequest(
                    self::CREATE_ORDER,
                    $this->collectPaymentData($orderInfo, $config, $notificationUrl, $total_price)
                ),
            ]);
        } catch (GuzzleException $e) {
            $this->logger->log('can\'t create order: ' . $e->getMessage(), LogLevel::ERROR);
        }

        if (!isset($response)) {
            return [];
        }

        return $this->parseResponse($response);
    }

    /**
     * @param $url
     * @param $callBackData
     * @return mixed
     * @throws Exception
     */
    public function proceedRefund($url, $callBackData)
    {
        $refundResult = [];
        try {
            $notificationUrl = $this->getNotificationUrl();
            $response = $this->client->request(self::METHOD_POST, self::CREATE_REFUND, [
                'headers' => $this->getJwsRequestHeaders(),
                'body' => $this->signJwsRequest(
                    self::CREATE_REFUND,
                    $this->collectRefundData($notificationUrl, $callBackData)
                ),
            ]);

            $refundResult = $this->parseResponse($response);
        } catch (GuzzleException $e) {
            $this->logger->log('Cannot refund: ' . $e->getMessage(), LogLevel::ERROR);
            throw new Exception($this->language->get('error_refund_not_allowed'));
        }

        return $refundResult;
    }

    /**
     * @param array $completionData
     * @return array
     * @throws Exception
     */
    public function proceedCompletion(array $completionData): array
    {
        $completionResult = [];
        try {
            $notificationUrl = $this->getNotificationUrl();
            $body = [
                'merchantRequestId' => uniqid(),
                'merchantId' => (string)($completionData['merchant_id'] ?? ''),
                'originalOperationId' => (string)($completionData['operation_id'] ?? ''),
                'coinAmount' => (string)($completionData['coin_amount'] ?? ''),
                'date' => $this->dateTime::getSiteDateTime($this->config),
                'notificationUrl' => $notificationUrl,
            ];

            $response = $this->client->request(self::METHOD_POST, self::COMPLETION, [
                'headers' => $this->getJwsRequestHeaders(),
                'body' => $this->signJwsRequest(self::COMPLETION, $body),
            ]);

            $completionResult = $this->parseResponse($response);
        } catch (GuzzleException $e) {
            $this->logger->log('Cannot proceed completion: ' . $e->getMessage(), LogLevel::ERROR);
            throw new Exception($this->language->get('error_completion_failed'));
        }

        return $completionResult;
    }

    /**
     * @param string $hpp_order_id
     * @return array
     * @throws Throwable
     */
    public function checkOperationStatus(string $hpp_order_id)
    {
        try {
            $response = $this->client->request(self::METHOD_POST, self::CHECK_OPERATIONS, [
                'headers' => $this->getJwsRequestHeaders(),
                'body' => $this->signJwsRequest(self::CHECK_OPERATIONS, ['hppOrderId' => $hpp_order_id]),
            ]);
        } catch (GuzzleException $e) {
            $this->logger->log('Check operation error: ' . $e->getMessage(), LogLevel::ERROR);
        }

        if (isset($response)) {
            return $this->parseResponse($response);
        }

        return [];
    }

    /**
     * @param array $orderInfo
     * @param object $config
     * @param $url
     * @param $total_price
     * @return array
     */
    private function collectPaymentData(array $orderInfo, object $config, $url, $total_price): array
    {
        $customerData = [];
        if ($orderInfo) {
            $isoCode2 = !empty($orderInfo['payment_iso_code_2'])
                ? $orderInfo['payment_iso_code_2']
                : ($orderInfo['shipping_iso_code_2'] ?? '');

            $customerData = [
                'senderFirstName' => $orderInfo['firstname'] ?? '',
                'senderLastName' => $orderInfo['lastname'] ?? '',
                'senderEmail' => $orderInfo['email'] ?? '',
                'senderCountry' => !empty($isoCode2)
                    ? $this->countryCodeProvider->getCountryNumericCodeByAlpha2($isoCode2)
                    : '',
                'senderRegion' => !empty($orderInfo['payment_zone'])
                    ? $orderInfo['payment_zone']
                    : ($orderInfo['shipping_zone'] ?? ''),
                'senderCity' => !empty($orderInfo['payment_city'])
                    ? $orderInfo['payment_city']
                    : ($orderInfo['shipping_city'] ?? ''),
                'senderStreet' => !empty($orderInfo['payment_address_1'])
                    ? $orderInfo['payment_address_1']
                    : ($orderInfo['shipping_address_1'] ?? ''),
                'senderAdditionalAddress' => !empty($orderInfo['payment_address_2'])
                    ? $orderInfo['payment_address_2']
                    : ($orderInfo['shipping_address_2'] ?? ''),
                'senderIp' => $orderInfo['ip'] ?? $this->request->server['REMOTE_ADDR'],
                'senderPhone' => $orderInfo['telephone'] ?? '',
                'senderZipCode' => !empty($orderInfo['payment_postcode'])
                    ? $orderInfo['payment_postcode']
                    : ($orderInfo['shipping_postcode'] ?? ''),
            ];
        }

        if (!empty($orderInfo['customer_id'])) {
            $customerData['senderCustomerId'] = (string)$orderInfo['customer_id'];
        } else {
            $customerData['senderCustomerId'] = 'guest_' . ($orderInfo['order_id'] ?? uniqid());
        }

        $hppPayType = $config->get('payment_alliance_hpp_type') ?? self::HPP_PAY_TYPE_PURCHASE;

        $paymentData = [
            'merchantId' => $config->get('payment_alliance_merchant_id'),
            'hppPayType' => $hppPayType,
            'failUrl' => $config->get('payment_alliance_fail_url'),
            'successUrl' => $config->get('payment_alliance_success_url'),
            'merchantRequestId' => uniqid(),
            'statusPageType' => 'STATUS_TIMER_PAGE',
            'paymentMethods' => ['CARD', 'APPLE_PAY', 'GOOGLE_PAY'],
            'customerData' => $this->validateCustomerData($customerData, $this->validationRules),
            'coinAmount' => (int)round($total_price * 100),
            'notificationUrl' => $url,
        ];

        if ($hppPayType === self::HPP_PAY_TYPE_A2A) {
            $paymentData['hppPaymentType'] = self::HPP_PAY_TYPE_A2A;
            $paymentData['directType'] = self::A2A_DIRECT_TYPE;
            $paymentData['priorityBankCode'] = self::A2A_PRIORITY_BANK_CODE;
            $paymentData['merchantComment'] = 'Payment for order #' . ($orderInfo['order_id'] ?? '');
        }

        if ($hppPayType === self::HPP_PAY_TYPE_PURCHASE || $hppPayType === self::HPP_PAY_TYPE_PREAUTH) {
            $currencyCodeStr = $orderInfo['currency_code'] ?? 'UAH';
            $paymentData['currencyCode'] = self::getCurrencyCode($currencyCodeStr);
        }

        if ($hppPayType === self::HPP_PAY_TYPE_PREAUTH) {
            $preAuthExpDateOption = $config->get('payment_alliance_preauth_exp_date') ?? '2h';
            $paymentData['preAuthExpDate'] = $this->calculatePreAuthExpDate($preAuthExpDateOption);
        }

        return $paymentData;
    }

    /**
     * @param string $url
     * @param array $callBackData
     * @return array
     */
    private function collectRefundData(string $url, array $callBackData): array
    {
        return [
            'merchantRequestId' => (string)$callBackData['merchant_request_id'] ?? '',
            'operationId' => (string)$callBackData['operation_id'] ?? '',
            'merchantId' => (string)$callBackData['merchant_id'] ?? '',
            'coinAmount' => (string)$callBackData['coin_amount'] ?? '',
            'notificationUrl' => $url,
            'date' => $this->dateTime::getSiteDateTime($this->config),
        ];
    }

    /**
     * @param string $option
     * @return string
     * @throws \Exception
     */
    private function calculatePreAuthExpDate(string $option): string
    {
        $timezone = $this->config->get('config_timezone') ?: 'UTC';
        $datetime = new \DateTime('now', new \DateTimeZone($timezone));

        // Parse option: digit(s) + unit letter (h = hours, d = days)
        if (preg_match('/^(\d+)([hd])$/', $option, $matches)) {
            $amount = (int)$matches[1];
            $unit = $matches[2] === 'h' ? 'hours' : 'days';
            $datetime->modify('+' . $amount . ' ' . $unit);
        } else {
            $datetime->modify('+2 hours');
        }

        $datetime->modify('+' . \Opencart\System\Helper\Alliance\Config::PREAUTH_EXP_DATE_BUFFER_SECONDS . ' seconds');

        $formatted = $datetime->format('Y-m-d H:i:s.vP');

        return preg_replace('/(\.\d{2})\d/', '$1', $formatted);
    }

    /**
     * @return string
     */
    private function getNotificationUrl(): string
    {
        $baseUrl = defined('HTTP_CATALOG') ? HTTP_CATALOG : HTTP_SERVER;

        return rtrim($baseUrl, '/') . '/index.php?route=' . self::NOTIFICATION_URL_ROUTE;
    }

    /**
     * @return array[]
     */
    public function getValidationRules(): array
    {
        return $this->validationRules;
    }

    public function validateCustomerData(array $data, array $rules): array
    {
        $validated = [];

        foreach ($rules as $field => $rule) {
            $value = isset($data[$field]) ? trim($data[$field]) : '';
            $is_valid = true;
            $error_reason = '';

            if ($rule['required'] && $value === '') {
                $is_valid = false;
                $error_reason = $this->language->get('error_validation_required');
            }

            if ($value !== '' && $is_valid) {
                if (isset($rule['type']) && $rule['type'] === 'numeric_string') {
                    $value = preg_replace('/\D/', '', $value);
                }

                if (isset($rule['no_only_digits']) && preg_match('/^\d+$/', $value)) {
                    $is_valid = false;
                    $error_reason = $this->language->get('error_validation_only_digits');
                }

                if ($is_valid && isset($rule['pattern'])) {
                    if (!preg_match($rule['pattern'], $value)) {
                        $is_valid = false;
                        $error_reason = $this->language->get('error_validation_pattern');
                    }
                }

                if ($is_valid && isset($rule['stop_words'])) {
                    if (in_array(mb_strtoupper($value), $rule['stop_words'])) {
                        $is_valid = false;
                        $error_reason = sprintf(
                            $this->language->get('error_validation_stop_word'),
                            $value
                        );
                    }
                }

                if ($is_valid && isset($rule['max_len'])) {
                    if (mb_strlen($value) > $rule['max_len']) {
                        $is_valid = false;
                        $error_reason = sprintf(
                            $this->language->get('error_validation_max_len'),
                            $rule['max_len']
                        );
                    }
                }
            }

            if ($is_valid && $value !== '') {
                $validated[$field] = $value;
            } else {
                if ($value !== '' || $rule['required']) {
                    $this->logger->log(sprintf(
                        'Payment Aggregation Error: Field [%s] removed. Reason: %s. Value was: [%s]',
                        $field,
                        $error_reason,
                        $value
                    ), LogLevel::WARNING);
                }
            }
        }

        return $validated;
    }
}
