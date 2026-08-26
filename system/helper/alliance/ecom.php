<?php

namespace Opencart\System\Helper\Alliance;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Opencart\System\Engine\Registry;
use Opencart\System\Helper\Alliance\Config;
use Opencart\System\Helper\Alliance\log\AllianceLogger;
use Psr\Log\LogLevel;
use SimpleJWT\JWE;
use SimpleJWT\Keys\KeyFactory;
use SimpleJWT\Keys\KeySet;

/**
 * Class Ecom.
 */
class Ecom
{
    const AUTHORIZE_VIRTUAL_DEVICE = 'api-gateway/authorize_virtual_device';

    const CREATE_ORDER = 'ecom/execute_request/hpp/v1/create-order';

    const CREATE_REFUND = 'ecom/execute_request/payments/v3/refund';

    const COMPLETION = 'ecom/execute_request/payments/v1/completion';

    const CHECK_OPERATIONS = 'ecom/execute_request/hpp/v1/operations';

    const NOTIFICATION_URL_ROUTE = 'extension/alliance/payment/alliance|notifyCallback';

    const METHOD_POST = 'POST';

    const ALGORITHM = 'ECDH-ES+A256KW';

    const ENCRYPTION = 'A256GCM';

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

    public function __construct(Registry $registry)
    {
        $this->config = $registry->get('config');
        $this->language = $registry->get('language');
        $this->language->load('extension/alliance/payment/alliance');
        $loader = $registry->get('load');
        $loader->helper('extension/alliance/alliance/datetime');
        $loader->helper('extension/alliance/alliance/log/alliancelogger');
        $loader->helper('extension/alliance/alliance/alliancecountrycodeprovider');
        $loader->helper('extension/alliance/alliance/config');
        $this->logger = new AllianceLogger($registry);
        $this->dateTime = new Datetime();
        $this->client = new Client(['base_uri' => $this->config->get('payment_alliance_url')]);
        $this->countryCodeProvider = new AllianceCountryCodeProvider();

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
     * @throws GuzzleException
     * @throws Exception
     */
    public function authorizeByVirtualDevice(string $payment_alliance_service_code_id)
    {
        try {
            $response = $this->client->request(self::METHOD_POST, self::AUTHORIZE_VIRTUAL_DEVICE, [
                'headers' => $this->getHeaders(),
                'json' => [
                    'serviceCode' => $payment_alliance_service_code_id,
                ]
            ]);
        } catch (GuzzleException $e) {
            $this->logger->log('authorize error: ' . $e->getMessage(), LogLevel::ERROR);
        }

        $contents = $response->getBody()->getContents();
        $contents = json_decode($contents, true);

        if (!isset($contents['jwe'])) {
            $this->logger->log('No token: ' . $e->getMessage(), LogLevel::ERROR);
        }

        return $contents['jwe'];
    }

    /**
     * @param string $jwe_token
     * @return array
     */
    public function decryptResponse(string $jwe_token): array
    {
        $set = new KeySet();
        $keyFactory = new KeyFactory();

        $json = [
            "kty" => "EC",
            "d" => $this->config->get("payment_alliance_jwt_param_d"),
            "use" => "enc",
            "crv" => "P-384",
            "x" => $this->config->get("payment_alliance_jwt_param_x"),
            "y" => $this->config->get("payment_alliance_jwt_param_y"),
            "alg" => self::ALGORITHM,
        ];

        $keys = $keyFactory->create($json, alg: self::ALGORITHM);
        $set->add($keys);

        $jwe_decrypted = JWE::decrypt($jwe_token, $set, self::ALGORITHM);

        return (array)json_decode($jwe_decrypted->getPlaintext());
    }

    /**
     * @param array $publicServerKey
     * @param string $payload
     * @return string
     */
    public function encryptRequest(array $publicServerKey, string $payload): string
    {
        $headers = [
            'alg' => self::ALGORITHM,
            'enc' => self::ENCRYPTION
        ];

        $key = KeyFactory::create($publicServerKey, alg: self::ALGORITHM);
        $keySet = new KeySet();
        $keySet->add($key);
        $jwe = new JWE($headers, $payload);

        try {
            $token = $jwe->encrypt($keySet);
        } catch (Exception $e) {
            $this->logger->log('Can not encrypt JWE' . $e->getMessage(), LogLevel::ERROR);
        }

        return $token;
    }

    /**
     * @param array $decrypt_auth
     * @param array $orderInfo
     * @param object $config
     * @param $url
     * @param $total_price
     * @return mixed
     */
    public function createCardHppOrder(array $decrypt_auth, array $orderInfo, object $config, $url, $total_price)
    {
        try {
            $notificationUrl = $this->getNotificationUrl();
            $response = $this->client->request(self::METHOD_POST, self::CREATE_ORDER, [
                'headers' => $this->getHeaders($decrypt_auth),
                'json' => $this->collectPaymentData($orderInfo, $config, $notificationUrl, $total_price),
            ]);
        } catch (GuzzleException $e) {
            $this->logger->log('can\'t create order: ' . $e->getMessage(), LogLevel::ERROR);
        }

        $contents = $response->getBody()->getContents();

        return json_decode($contents, true);
    }

    /**
     * @param array $decrypt_auth
     * @param $url
     * @param $callBackData
     * @return mixed
     * @throws Exception
     */
    public function proceedRefund(array $decrypt_auth, $url, $callBackData)
    {
        $refundResult = [];
        try {
            $notificationUrl = $this->getNotificationUrl();
            $serverPublicKey = json_decode(json_encode($this->getServerPublicKey($decrypt_auth)), true);
            $headers = $this->getHeaders($decrypt_auth, 'text/plain');

            if (!empty($serverPublicKey)) {
                $payload = json_encode($this->collectRefundData(
                    $notificationUrl,
                    $callBackData
                ), JSON_PRETTY_PRINT);
                $refundEncryptBody = $this->encryptRequest(
                    $serverPublicKey,
                    $payload
                );
                $response = $this->client->request(self::METHOD_POST, self::CREATE_REFUND, [
                    'headers' => $headers,
                    'body' => $refundEncryptBody,
                ]);

                $contents = json_decode($response->getBody()->getContents(), true);

                if (isset($contents['jwe'])) {
                    $refundResult = $this->decryptResponse($contents['jwe']);
                }
            }
        } catch (GuzzleException $e) {
            $this->logger->log('Cannot refund: ' . $e->getMessage(), LogLevel::ERROR);
            throw new Exception($this->language->get('error_refund_not_allowed'));
        }

        return $refundResult;
    }

    /**
     * @param array $decrypt_auth
     * @param array $completionData
     * @return array
     * @throws Exception
     */
    public function proceedCompletion(array $decrypt_auth, array $completionData): array
    {
        $completionResult = [];
        try {
            $notificationUrl = $this->getNotificationUrl();
            $serverPublicKey = json_decode(json_encode($this->getServerPublicKey($decrypt_auth)), true);
            $headers = $this->getHeaders($decrypt_auth, 'text/plain');

            if (!empty($serverPublicKey)) {
                $payload = json_encode([
                    'merchantRequestId' => uniqid(),
                    'merchantId' => (string)($completionData['merchant_id'] ?? ''),
                    'originalOperationId' => (string)($completionData['operation_id'] ?? ''),
                    'coinAmount' => (string)($completionData['coin_amount'] ?? ''),
                    'date' => $this->dateTime::getSiteDateTime($this->config),
                    'notificationUrl' => $notificationUrl,
                ], JSON_PRETTY_PRINT);

                $encryptedBody = $this->encryptRequest($serverPublicKey, $payload);

                $response = $this->client->request(self::METHOD_POST, self::COMPLETION, [
                    'headers' => $headers,
                    'body' => $encryptedBody,
                ]);

                $contents = json_decode($response->getBody()->getContents(), true);

                if (isset($contents['jwe'])) {
                    $completionResult = $this->decryptResponse($contents['jwe']);
                }
            }
        } catch (GuzzleException $e) {
            $this->logger->log('Cannot proceed completion: ' . $e->getMessage(), LogLevel::ERROR);
            throw new Exception($this->language->get('error_completion_failed'));
        }

        return $completionResult;
    }

    /**
     * @throws Exception
     */
    public function checkOperationStatus(array $decrypt_auth, string $hpp_order_id)
    {
        try {
            $response = $this->client->request(self::METHOD_POST, self::CHECK_OPERATIONS, [
                'headers' => $this->getHeaders($decrypt_auth),
                'json' => [
                    "hppOrderId" => $hpp_order_id,
                ]
            ]);
        } catch (GuzzleException $e) {
            $this->logger->log('Check operation error: ' . $e->getMessage(), LogLevel::ERROR);
        }

        if (isset($response)) {
            $contents = $response->getBody()->getContents();

            return json_decode($contents, true) ?? [];
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
     * @param array $decrypt_auth
     * @param $contentType
     * @return array
     */
    private function getHeaders(array $decrypt_auth = [], $contentType = 'application/json'): array
    {
        $headers["Content-Type"] = $contentType;
        $headers["x-api_version"] = "v1";
        $headers["x-request_id"] = uniqid();

        if ($decrypt_auth) {
            $headers['x-device_id'] = $decrypt_auth['deviceId'];
        }

        if ($decrypt_auth) {
            $headers['x-refresh_token'] = $decrypt_auth['refreshToken'];
        }

        return $headers;
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
     * @param array $decrypt_auth
     * @return mixed|null
     */
    private function getServerPublicKey(array $decrypt_auth = [])
    {
        return $decrypt_auth['serverPublic'] ?? null;
    }

    /**
     * @param array $data
     * @param array $rules
     * @return array
     */
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
                    $this->log->write(sprintf(
                        'Payment Aggregation Error: Field [%s] removed. Reason: %s. Value was: [%s]',
                        $field,
                        $error_reason,
                        $value
                    ));
                }
            }
        }

        return $validated;
    }
}
