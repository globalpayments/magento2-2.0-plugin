<?php

namespace GlobalPayments\PaymentGateway\Helper;

use Magento\Framework\App\{CacheInterface, RequestInterface};
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Magento\Framework\Lock\LockManagerInterface;
use Magento\Store\Model\ScopeInterface;
use Psr\Log\LoggerInterface;

/**
 * 3DS Security Helper - Prevents carding attacks on 3DS enrollment endpoints
 *
 * Security measures:
 * 1. Cryptographically signed tokens (HMAC-SHA256)
 * 2. Token bound to client IP address
 * 3. Token expiration (5 minutes)
 * 4. Token usage limits (max 2 uses per token)
 * 5. Rate limiting (2 requests per minute per IP)
 * 6. Hourly limits (10 requests per hour per IP)
 */
class ThreeDSecureSecurity
{
    /**
     * Token expiration time in seconds (5 minutes)
     */
    private const TOKEN_EXPIRY_SECONDS = 300;

    /**
     * Maximum uses per token
     */
    private const MAX_TOKEN_USES = 2;

    /**
     * Rate limit: requests per minute
     */
    private const RATE_LIMIT_PER_MINUTE = 2;

    /**
     * Hourly limit: requests per hour
     */
    private const HOURLY_LIMIT = 10;

    /**
     * Cache key prefix for token usage
     */
    private const CACHE_PREFIX_TOKEN = 'gp_3ds_token_';

    /**
     * Cache key prefix for rate limiting
     */
    private const CACHE_PREFIX_RATE = 'gp_3ds_rate_';

    /**
     * Cache key prefix for hourly limiting
     */
    private const CACHE_PREFIX_HOURLY = 'gp_3ds_hourly_';

    /**
     * Cache tag for 3DS security data
     */
    private const CACHE_TAG = 'GP_3DS_SECURITY';

    /**
     * Config path for GPAPI sandbox mode
     */
    private const CONFIG_PATH_SANDBOX_MODE = 'payment/globalpayments_paymentgateway_gpApi/sandbox_mode';

    /**
     * Config path for GPAPI app_key (production)
     */
    private const CONFIG_PATH_APP_KEY = 'payment/globalpayments_paymentgateway_gpApi/app_key';

    /**
     * Config path for GPAPI sandbox_app_key
     */
    private const CONFIG_PATH_SANDBOX_APP_KEY = 'payment/globalpayments_paymentgateway_gpApi/sandbox_app_key';

    /**
     * Lock timeout in seconds for atomic counter operations
     */
    private const LOCK_TIMEOUT_SECONDS = 5;

    /**
     * @var RequestInterface
     */
    private $request;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var CacheInterface
     */
    private $cache;

    /**
     * @var LockManagerInterface
     */
    private $lockManager;

    /**
     * @var RemoteAddress
     */
    private $remoteAddress;

    /**
     * @param RequestInterface $request
     * @param LoggerInterface $logger
     * @param ScopeConfigInterface $scopeConfig
     * @param CacheInterface $cache
     * @param LockManagerInterface $lockManager
     * @param RemoteAddress $remoteAddress
     */
    public function __construct(
        RequestInterface $request,
        LoggerInterface $logger,
        ScopeConfigInterface $scopeConfig,
        CacheInterface $cache,
        LockManagerInterface $lockManager,
        RemoteAddress $remoteAddress
    ) {
        $this->request = $request;
        $this->logger = $logger;
        $this->scopeConfig = $scopeConfig;
        $this->cache = $cache;
        $this->lockManager = $lockManager;
        $this->remoteAddress = $remoteAddress;
    }

    /**
     * Generate a cryptographically signed security token bound to the client IP.
     *
     * Token format: timestamp:ip_hash:signature
     *
     * @return string
     */
    public function generateSecurityToken(): string
    {
        $timestamp = time();
        $clientIp = $this->getClientIp();
        $ipHash = substr(hash_hmac('sha256', $clientIp, $this->getSecretKey()), 0, 16);

        $data = 'gp3ds_' . $timestamp . '_' . $ipHash;
        $signature = hash_hmac('sha256', $data, $this->getSecretKey());

        return $timestamp . ':' . $ipHash . ':' . $signature;
    }

    /**
     * Validate the security token from the request.
     *
     * @return array ['valid' => bool, 'error' => string|null]
     */
    public function validateSecurityToken(): array
    {
        $token = $this->request->getParam('gp3ds_token');

        if (empty($token)) {
            return [
                'valid' => false,
                'error' => __('Security token missing. Please refresh the page and try again.')
            ];
        }

        // Parse token format: timestamp:ip_hash:signature
        $parts = explode(':', $token);
        if (count($parts) !== 3) {
            $this->logger->warning('3DS Security: Invalid token format', ['token_parts' => count($parts)]);
            return [
                'valid' => false,
                'error' => __('Invalid security token. Please refresh the page and try again.')
            ];
        }

        $timestamp = (int) $parts[0];
        $tokenIpHash = $parts[1];
        $providedSignature = $parts[2];

        // Verify the request comes from the same IP that generated the token
        $clientIp = $this->getClientIp();
        $currentIpHash = substr(hash_hmac('sha256', $clientIp, $this->getSecretKey()), 0, 16);

        if (!hash_equals($tokenIpHash, $currentIpHash)) {
            $this->logger->warning('3DS Security: IP mismatch', [
                'expected_hash' => $tokenIpHash,
                'current_hash' => $currentIpHash
            ]);
            return [
                'valid' => false,
                'error' => __('Security verification failed. Please refresh the page and try again.')
            ];
        }

        // Verify the HMAC signature (proves token wasn't forged)
        $expectedData = 'gp3ds_' . $timestamp . '_' . $tokenIpHash;
        $expectedSignature = hash_hmac('sha256', $expectedData, $this->getSecretKey());

        if (!hash_equals($expectedSignature, $providedSignature)) {
            $this->logger->warning('3DS Security: Invalid signature');
            return [
                'valid' => false,
                'error' => __('Security verification failed. Please refresh the page and try again.')
            ];
        }

        // Check token expiration (5 minutes)
        $tokenAge = time() - $timestamp;
        if ($tokenAge > self::TOKEN_EXPIRY_SECONDS || $tokenAge < 0) {
            $this->logger->info('3DS Security: Token expired', ['age' => $tokenAge]);
            return [
                'valid' => false,
                'error' => __('Security token expired. Please refresh the page and try again.')
            ];
        }

        // Check token usage limit (atomic: lock prevents concurrent bypasses)
        $usageKey = self::CACHE_PREFIX_TOKEN . hash('sha256', $token);
        $remainingTtl = max(1, self::TOKEN_EXPIRY_SECONDS - $tokenAge);
        $usageResult = $this->checkAndIncrementCounter($usageKey, self::MAX_TOKEN_USES, $remainingTtl);

        if ($usageResult['exceeded']) {
            $this->logger->warning('3DS Security: Token usage exhausted', ['uses' => $usageResult['count']]);
            return [
                'valid' => false,
                'error' => __('Security token exhausted. Please refresh the page and try again.')
            ];
        }

        // Rate limiting: max requests per minute per IP (atomic)
        $rateLimitKey = self::CACHE_PREFIX_RATE . hash('sha256', $clientIp);
        $rateLimitResult = $this->checkAndIncrementCounter($rateLimitKey, self::RATE_LIMIT_PER_MINUTE, 60);

        if ($rateLimitResult['exceeded']) {
            $this->logger->warning('3DS Security: Rate limit exceeded', [
                'ip' => $this->maskIp($clientIp),
                'count' => $rateLimitResult['count']
            ]);
            return [
                'valid' => false,
                'error' => __('Too many requests. Please wait a moment before trying again.')
            ];
        }

        // Hourly limit: max requests per hour per IP (atomic)
        $hourlyKey = self::CACHE_PREFIX_HOURLY . hash('sha256', $clientIp);
        $hourlyResult = $this->checkAndIncrementCounter($hourlyKey, self::HOURLY_LIMIT, 3600);

        if ($hourlyResult['exceeded']) {
            $this->logger->warning('3DS Security: Hourly limit exceeded', [
                'ip' => $this->maskIp($clientIp),
                'count' => $hourlyResult['count']
            ]);
            return [
                'valid' => false,
                'error' => __('Request limit reached. Please try again later.')
            ];
        }

        return ['valid' => true, 'error' => null];
    }

    /**
     * Atomically check a counter against a limit and increment if not exceeded.
     *
     * A per-key lock prevents concurrent requests from all reading the same
     * pre-increment value and simultaneously bypassing the limit check.
     *
     * @param string $cacheKey Cache key for the counter
     * @param int $limit Maximum allowed count
     * @param int $ttl Cache TTL in seconds
     * @return array{count: int, exceeded: bool}
     *               'count' is the current value read from cache.
     *               'exceeded' is true when count >= limit (no increment performed);
     *               false when count < limit (counter was incremented).
     */
    private function checkAndIncrementCounter(string $cacheKey, int $limit, int $ttl): array
    {
        $lockKey = 'gp_3ds_lock_' . $cacheKey;
        $lockAcquired = $this->lockManager->lock($lockKey, self::LOCK_TIMEOUT_SECONDS);
        if (!$lockAcquired) {
            $this->logger->warning('3DS Security: Could not acquire lock for counter', ['key' => $cacheKey]);
        }
        try {
            $count = (int) $this->cache->load($cacheKey);
            if ($count >= $limit) {
                return ['count' => $count, 'exceeded' => true];
            }
            $this->cache->save(
                (string) ($count + 1),
                $cacheKey,
                [self::CACHE_TAG],
                $ttl
            );
            return ['count' => $count, 'exceeded' => false];
        } finally {
            if ($lockAcquired) {
                $this->lockManager->unlock($lockKey);
            }
        }
    }

    /**
     * Get the client IP address.
     *
     * Uses Magento's RemoteAddress which handles proxy headers
     * and respects trusted proxy configuration.
     *
     * @return string
     */
    public function getClientIp(): string
    {
        return $this->remoteAddress->getRemoteAddress() ?: '0.0.0.0';
    }

    /**
     * Get the secret key for HMAC signing.
     *
     * Uses the GPAPI gateway's configured app_key for security.
     * This is preferred over crypt/key because if app_key is unavailable,
     * the payment flow wouldn't reach 3DS processing anyway.
     *
     * Reads directly from ScopeConfig to avoid circular dependency with Gateway\Config.
     *
     * @return string
     */
    private function getSecretKey(): string
    {
        try {
            $isSandbox = $this->scopeConfig->isSetFlag(
                self::CONFIG_PATH_SANDBOX_MODE,
                ScopeInterface::SCOPE_STORE
            );

            $configPath = $isSandbox
                ? self::CONFIG_PATH_SANDBOX_APP_KEY
                : self::CONFIG_PATH_APP_KEY;

            $appKey = $this->scopeConfig->getValue($configPath, ScopeInterface::SCOPE_STORE);

            if (!empty($appKey)) {
                return $appKey;
            }
        } catch (\Exception $e) {
            $this->logger->error('3DS Security: Failed to get app_key', ['error' => $e->getMessage()]);
        }

        // This should not happen in normal operation - if app_key is unavailable,
        // the payment gateway wouldn't work and we wouldn't reach 3DS processing.
        // Throwing an exception to fail securely rather than using an insecure fallback.
        $this->logger->warning('3DS Security: app_key is empty; cannot generate security token');
        throw new \RuntimeException('3DS Security: Unable to retrieve app_key for token signing');
    }

    /**
     * Mask IP address for logging (privacy).
     *
     * @param string $ip
     * @return string
     */
    private function maskIp(string $ip): string
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $parts = explode('.', $ip);
            return $parts[0] . '.' . $parts[1] . '.xxx.xxx';
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return substr($ip, 0, 10) . ':xxxx:xxxx:xxxx';
        }

        return 'xxx.xxx.xxx.xxx';
    }
}
