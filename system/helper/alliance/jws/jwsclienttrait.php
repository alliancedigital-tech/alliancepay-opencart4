<?php

declare(strict_types=1);

namespace Opencart\System\Helper\Alliance\Jws;

use Exception;
use Opencart\System\Helper\Alliance\RequestIdentification\GenerateRequestIdentification;
use Psr\Log\LogLevel;
use SimpleJWT\InvalidTokenException;
use SimpleJWT\JWT;
use SimpleJWT\Keys\ECKey;
use SimpleJWT\Keys\KeyFactory;
use SimpleJWT\Keys\KeySet;
use Throwable;

/**
 * Trait JwsClientTrait.
 */
trait JwsClientTrait
{
    /**
     * @param string $endpoint
     * @param array $body
     * @param string $contentType
     * @return string
     * @throws Throwable
     */
    private function signJwsRequest(string $endpoint, array $body, string $contentType = 'application/jws'): string
    {
        $privateKeyPem = $this->config->get('payment_alliance_jws_private_key');
        $kid = $this->config->get('payment_alliance_jws_key_id');

        if (empty($privateKeyPem)) {
            throw new \RuntimeException('JWS Private Key is not configured.');
        }
        if (empty($kid)) {
            throw new \RuntimeException('JWS Key ID is not configured.');
        }

        try {
            $key = KeyFactory::create($privateKeyPem, 'pem');
            $key->setKeyId($kid);
            $keySet = new KeySet();
            $keySet->add($key);

            $jwt = new JWT(
                [
                    'alg' => 'RS256',
                    'ts' => (string)(int)(microtime(true) * 1000),
                    'targetUrl' => '/' . ltrim($endpoint, '/'),
                    'kid' => $kid,
                    'jti' => GenerateRequestIdentification::generateRequestId(),
                ],
                $body
            );

            return $jwt->encode($keySet, null, false);
        } catch (Throwable $e) {
            $this->logger->log('JWS signing failed: ' . $e->getMessage(), LogLevel::ERROR);
            throw $e;
        }
    }

    /**
     * @param string $contentType
     * @param bool $includeApiVersion
     * @return array
     * @throws Exception
     */
    private function getJwsRequestHeaders(string $contentType = 'application/jws', bool $includeApiVersion = true): array
    {
        $headers = [
            'Content-Type' => $contentType,
            'Accept' => 'application/json',
            'x-request_id' => GenerateRequestIdentification::generateRequestId(),
        ];

        if ($includeApiVersion) {
            $headers['x-api_version'] = 'v1';
        }

        return $headers;
    }

    /**
     * @param string $jws
     * @return array
     */
    public function verifyJwsToken(string $jws): array
    {
        try {
            $deserialised = JWT::deserialise($jws);
            $headers = $deserialised['signatures'][0]['headers'];
            $kid = $headers['kid'] ?? null;
            $alg = $headers['alg'] ?? null;
        } catch (Throwable $e) {
            throw new \RuntimeException('JWS parse error: ' . $e->getMessage());
        }

        if (empty($kid) || empty($alg)) {
            throw new \RuntimeException('JWS: missing kid or alg in header.');
        }

        $jwk = $this->fetchBankPublicKey($kid);

        $key = new ECKey($jwk, 'php');
        $key->setKeyId($kid);
        $keySet = new KeySet();
        $keySet->add($key);

        try {
            $jwt = JWT::decode($jws, $keySet, $alg);
        } catch (InvalidTokenException $e) {
            throw new \RuntimeException('JWS verification failed: ' . $e->getMessage());
        }

        return $jwt->getClaims();
    }

    /**
     * @param string $kid
     * @return array
     */
    private function fetchBankPublicKey(string $kid): array
    {
        $cacheKey = 'alb_bank_pubkey_' . md5($kid);
        $cached = $this->cache ? $this->cache->get($cacheKey) : null;

        if ($cached) {
            return json_decode($cached, true);
        }

        $response = $this->client->request(self::METHOD_POST, self::GET_PUBLIC_KEY, [
            'headers' => ['Content-Type' => 'application/json', 'Accept' => 'application/json'],
            'json' => ['kid' => $kid],
        ]);
        $jwk = json_decode($response->getBody()->getContents(), true);

        if (empty($jwk['kty']) || empty($jwk['crv']) || empty($jwk['x']) || empty($jwk['y'])) {
            throw new \RuntimeException('Invalid public key response from bank for kid=' . $kid);
        }

        if ($this->cache) {
            $this->cache->set($cacheKey, json_encode($jwk), self::BANK_PUBLIC_KEY_CACHE_TTL);
        }

        return $jwk;
    }

    /**
     * @param $response
     * @return array
     */
    private function parseResponse($response): array
    {
        $body = trim($response->getBody()->getContents());
        $dotCount = substr_count($body, '.');
        $isToken = $body !== '' && strpos($body, ' ') === false;

        if ($isToken && $dotCount === 2) {
            return $this->verifyJwsToken($body);
        }

        return json_decode($body, true) ?? [];
    }
}
