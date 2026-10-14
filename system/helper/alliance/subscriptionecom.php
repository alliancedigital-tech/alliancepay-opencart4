<?php

declare(strict_types=1);

namespace Opencart\System\Helper\Alliance;

require_once __DIR__ . '/jws/jwsclienttrait.php';

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Opencart\System\Engine\Registry;
use Opencart\System\Helper\Alliance\Jws\JwsClientTrait;
use Opencart\System\Helper\Alliance\log\AllianceLogger;
use Psr\Log\LogLevel;
use Throwable;

/**
 * Class SubscriptionEcom.
 */
class SubscriptionEcom
{
    use JwsClientTrait;

    const CONTENT_TYPE = 'application/jwt';

    const GET_PUBLIC_KEY = 'ecom/keys/public_key/get_v1';

    const BANK_PUBLIC_KEY_CACHE_TTL = 3600;

    const METHOD_POST = 'POST';

    const PLAN_CREATE = 'ecom/jws/scheduled_payments/plan/create_v1';

    const PLAN_UPDATE = 'ecom/jws/scheduled_payments/plan/update_v1';

    const PLAN_STATUS_UPDATE = 'ecom/jws/scheduled_payments/plan/status_update_v1';

    const PLAN_VERSION_UPDATE = 'ecom/jws/scheduled_payments/plan/version/update_v1';

    const VERSION_DELETE = 'ecom/jws/scheduled_payments/version/delete_v1';

    const SUBSCRIPTION_ORDER_CREATE = 'ecom/jws/scheduled_payments/subscription-orders/create_v1';

    const SUBSCRIPTION_ORDER_CANCEL = 'ecom/jws/scheduled_payments/subscription-orders/cancel_v1';

    const RECURRING_TRIGGER = 'ecom/jws/scheduled_payments/payments/recurring_subscription_v1';

    private ?object $config;

    private object $language;

    private $logger;

    private $client;

    private $cache;

    private Ecom $ecom;

    public function __construct(Registry $registry)
    {
        $this->config = $registry->get('config');
        $this->language = $registry->get('language');
        $this->language->load('extension/alliance/payment/alliance');
        $loader = $registry->get('load');
        $loader->helper('extension/alliance/alliance/log/alliancelogger');
        $loader->helper('extension/alliance/alliance/requestidentification/generaterequestidentification');
        $loader->helper('extension/alliance/alliance/ecom');
        $this->logger = new AllianceLogger($registry);
        $this->client = new Client(['base_uri' => $this->config->get('payment_alliance_url')]);
        $this->cache = $registry->get('cache');
        // Reused for validateCustomerData()/getCurrencyCode() — avoids duplicating
        // the customer-data validation rules a second time.
        $this->ecom = new Ecom($registry);

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
     * §4.1 step 1-2: create a plan. Caller must check plan_key dedup BEFORE calling
     * this, and MUST follow a success here with setInfiniteVersion() immediately
     * (§4.1 step 3) before recording the plan as active locally.
     *
     * @param array $body
     * @return array
     * @throws Throwable
     */
    public function createPlan(array $body): array
    {
        return $this->request(self::PLAN_CREATE, $body, 'Cannot create subscription plan');
    }

    /**
     * @param array $body
     * @return array
     * @throws Throwable
     */
    public function updatePlan(array $body): array
    {
        return $this->request(self::PLAN_UPDATE, $body, 'Cannot update subscription plan');
    }

    /**
     * @param array $body
     * @return array
     * @throws Throwable
     */
    public function updatePlanStatus(array $body): array
    {
        return $this->request(self::PLAN_STATUS_UPDATE, $body, 'Cannot update subscription plan status');
    }

    /**
     * §4.1 step 3 / §4.6: the mandatory "infinite schedule" call. Must be called
     * immediately after every plan create/update. A failure here must NOT be
     * reported to the admin as "plan created successfully" — the caller must mark
     * the local plan record as an error requiring manual follow-up instead.
     *
     * @param string $planId
     * @param string $versionId
     * @return array
     * @throws Throwable
     */
    public function setInfiniteVersion(string $planId, string $versionId): array
    {
        return $this->request(self::PLAN_VERSION_UPDATE, [
            'planId' => $planId,
            'versionId' => $versionId,
            'infiniteVersion' => true,
        ], 'Cannot set infinite version for subscription plan');
    }

    /**
     * @param array $body
     * @return array
     * @throws Throwable
     */
    public function deleteVersion(array $body): array
    {
        return $this->request(self::VERSION_DELETE, $body, 'Cannot delete subscription plan version');
    }

    /**
     * §4.2: create a customer subscription order. Response is expected to contain
     * `hppOrderId` and `redirectUrl` (same shape as the one-time HPP order create).
     *
     * @param array $body
     * @return array
     * @throws Throwable
     */
    public function createSubscriptionOrder(array $body): array
    {
        return $this->request(self::SUBSCRIPTION_ORDER_CREATE, $body, 'Cannot create subscription order');
    }

    /**
     * §4.5: cancel resolves by provider order id (not local subscription id).
     *
     * @param array $body
     * @return array
     * @throws Throwable
     */
    public function cancelSubscriptionOrder(array $body): array
    {
        return $this->request(self::SUBSCRIPTION_ORDER_CANCEL, $body, 'Cannot cancel subscription order');
    }

    /**
     * §4.4: admin-only manual trigger for a single scheduled payment. Never
     * called from customer-facing code.
     *
     * @param array $body
     * @return array
     * @throws Throwable
     */
    public function triggerRecurringPayment(array $body): array
    {
        return $this->request(self::RECURRING_TRIGGER, $body, 'Cannot trigger recurring subscription payment');
    }

    /**
     * §4.8: plan_key = hash(merchantId + productId + templateId + categoryIndicator
     * + normalized(rules)). `rules` must already be normalized by the caller
     * (stable key order, no volatile fields) before being passed in.
     *
     * @param string $merchantId
     * @param int $productId
     * @param int|null $templateId
     * @param string $categoryIndicator
     * @param array $rules
     * @return string
     */
    public static function computePlanKey(
        string $merchantId,
        int $productId,
        ?int $templateId,
        string $categoryIndicator,
        array $rules
    ): string {
        ksort($rules);

        return hash('sha256', implode('|', [
            $merchantId,
            $productId,
            $templateId ?? '',
            $categoryIndicator,
            json_encode($rules, JSON_UNESCAPED_UNICODE),
        ]));
    }

    /**
     * @param array $data
     * @param array $rules
     * @return array
     */
    public function validateCustomerData(array $data, array $rules): array
    {
        return $this->ecom->validateCustomerData($data, $rules);
    }

    /**
     * @param string $endpoint
     * @param array $body
     * @param string $errorMessage
     * @return array
     * @throws Throwable
     */
    private function request(string $endpoint, array $body, string $errorMessage): array
    {
        try {
            $response = $this->client->request(self::METHOD_POST, $endpoint, [
                'headers' => $this->getJwsRequestHeaders(self::CONTENT_TYPE, false),
                'body' => $this->signJwsRequest($endpoint, $body, self::CONTENT_TYPE),
            ]);
        } catch (GuzzleException $e) {
            $this->logger->log($errorMessage . ': ' . $e->getMessage(), LogLevel::ERROR);
            throw new Exception($errorMessage . '. ' . $this->language->get('error_technical'));
        }

        return $this->parseResponse($response);
    }
}
