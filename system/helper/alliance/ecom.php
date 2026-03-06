<?php

namespace Opencart\System\Helper\Alliance;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Opencart\System\Engine\Registry;
use Opencart\System\Helper\Alliance\log\AllianceLogger;
use Opencart\System\Library\Url;
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

    const CHECK_OPERATIONS = 'ecom/execute_request/hpp/v1/operations';

    const NOTIFICATION_URL_ROUTE = 'index.php?route=extension/alliance/payment/alliance|notifyCallback';

    const METHOD_POST = 'POST';
    const ALGORITHM = 'ECDH-ES+A256KW';

    const ENCRYPTION = 'A256GCM';

    private Datetime $dateTime;
    private Client $client;
    private ?object $config;
    private object $language;
    private $logger;

    public function __construct(Registry $registry)
    {
        $this->config = $registry->get('config');
        $this->language = $registry->get('language');
        $this->language->load('extension/alliance/payment/alliance');
        $loader = $registry->get('load');
        $loader->helper('extension/alliance/alliance/datetime');
        $loader->helper('extension/alliance/alliance/log/alliancelogger');
        $this->logger = new AllianceLogger($registry);
        $this->dateTime = new Datetime();
        $this->client = new Client(['base_uri' => $this->config->get('payment_alliance_url')]);

        $autoload = DIR_EXTENSION . 'alliance/system/library/vendor/autoload.php';

        if (is_file($autoload)) {
            require_once($autoload);
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
                    'serviceCode' =>  $payment_alliance_service_code_id,
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

        return (array) json_decode($jwe_decrypted->getPlaintext());
    }

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
     * @throws Exception
     */
    public function createCardHppOrder(array $decrypt_auth, object $session, object $config, $url, $total_price)
    {
        try {
            $notificationUrl = $this->getNotificationUrl($url);
            $response = $this->client->request(self::METHOD_POST, self::CREATE_ORDER, [
                'headers' => $this->getHeaders($decrypt_auth),
                'json' => $this->collectPaymentData($session, $config, $notificationUrl, $total_price),
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
            $notificationUrl = $this->getNotificationUrl($url);
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
     * @param object $session
     * @param object $config
     * @param $url
     * @param $total_price
     * @return array
     */
    private function collectPaymentData(object $session, object $config, $url, $total_price): array
    {
        if (!isset($session->data['customer']['customer_id'])){
            $customer_id = 'not_authorized_' . uniqid();
        } else {
            $customer_id = 'id_' . $session->data['customer']['customer_id'];
        }

        return [
            "merchantId" => $config->get("payment_alliance_merchant_id"),
            "hppPayType" => "PURCHASE",
            "failUrl" => $config->get("payment_alliance_fail_url"),
            "successUrl" => $config->get("payment_alliance_success_url"),
            "merchantRequestId" => uniqid(),
            "statusPageType" => "STATUS_TIMER_PAGE",
            "paymentMethods" => ["CARD", "APPLE_PAY", "GOOGLE_PAY"],
            "customerData" => [
                "senderCustomerId" => $customer_id,
            ],
            "coinAmount" => (int)($total_price * 100),
            "notificationUrl" => $url
        ];
    }

    private function collectRefundData(string $url, array $callBackData): array
    {
        return [
            'merchantRequestId' => (string)$callBackData['merchant_request_id'] ?? '',
            'operationId' => (string)$callBackData['operation_id'] ?? '',
            'merchantId' => (string)$callBackData['merchant_id'] ?? '',
            'coinAmount' => (string)$callBackData['coin_amount'] ?? '',
            'notificationUrl' => $url,
            'date' => $this->dateTime::getSiteDateTime($this->config)
        ];
    }

    private function getHeaders(array $decrypt_auth = [], $contentType = 'application/json'): array
    {
        //$headers["Accept"] = $contentType;
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
     * @param Url $url
     * @return string
     */
    private function getNotificationUrl(Url $url): string
    {
        return $url->link(self::NOTIFICATION_URL_ROUTE);
    }

    private function getServerPublicKey(array $decrypt_auth = [])
    {
        return $decrypt_auth['serverPublic'] ?? null;
    }
}
