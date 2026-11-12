<?php

namespace GlobalPayments\PaymentGateway\Helper;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Magento\Framework\Lock\LockManagerInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Store\Model\ScopeInterface;
use Psr\Log\LoggerInterface;

/**
 * 3DS Security Helper - prevents the 3DS endpoints being used as a carding oracle outside checkout.
 *
 * Each 3DS token is bound to the shopper's real cart (quote) when the checkout config is rendered,
 * so a 3DS call is only accepted when it belongs to a genuine, active checkout. The limits are
 * counted per cart, not per client.
 *
 * Security measures:
 * 1. Cryptographically signed tokens (HMAC-SHA256) carrying the quote id
 * 2. The signed quote must be active and non-empty at validation time
 * 3. Token expiration (5 minutes)
 * 4. Token usage limits (max 2 uses per token, scoped per 3DS endpoint)
 * 5. Rate limiting (5 requests per minute per cart)
 * 6. Hourly limits (10 requests per hour per cart)
 */
class ThreeDSecureSecurity
{
    /**
     * Token expiration time in seconds (5 minutes)
     */
    private const TOKEN_EXPIRY_SECONDS = 300;

    /**
     * Maximum uses per token, per endpoint scope.
     *
     * The counter is scoped per 3DS endpoint (see SCOPE_* constants), so this is the
     * budget for a single endpoint rather than the whole flow. The happy path calls
     * each endpoint once, so a cap of 2 leaves exactly one retry of headroom per
     * endpoint (covers double-submit / challenge re-entry).
     */
    private const MAX_TOKEN_USES = 2;

    /**
     * Rate limit: validations per minute per cart.
     */
    private const RATE_LIMIT_PER_MINUTE = 5;

    /**
     * Hourly limit: validations per hour per cart.
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
     * Prefix of the HMAC-signed payload. Distinct from the retired IP-bound format so a token
     * of the old shape can never verify.
     */
    private const SIGNED_DATA_PREFIX = 'gp3ds_q_';

    /**
     * Token-use counter scopes (one per 3DS endpoint).
     *
     * Because the checkout config builds both endpoint URLs back-to-back with a time()-based
     * token, both endpoints usually receive the identical token; scoping the counter per
     * endpoint stops the two legitimate validations (plus a retry) from sharing one bucket.
     */
    public const SCOPE_CHECK_ENROLLMENT = 'checkEnrollment';
    public const SCOPE_INITIATE_AUTH = 'initiateAuthentication';

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
     * Used for masked diagnostic log context only — never for a security decision.
     *
     * @var RemoteAddress
     */
    private $remoteAddress;

    /**
     * @var CartRepositoryInterface
     */
    private $cartRepository;

    /**
     * @var CheckoutSession
     */
    private $checkoutSession;

    /**
     * @param RequestInterface $request
     * @param LoggerInterface $logger
     * @param ScopeConfigInterface $scopeConfig
     * @param CacheInterface $cache
     * @param LockManagerInterface $lockManager
     * @param RemoteAddress $remoteAddress
     * @param CartRepositoryInterface $cartRepository
     * @param CheckoutSession $checkoutSession
     */
    public function __construct(
        RequestInterface $request,
        LoggerInterface $logger,
        ScopeConfigInterface $scopeConfig,
        CacheInterface $cache,
        LockManagerInterface $lockManager,
        RemoteAddress $remoteAddress,
        CartRepositoryInterface $cartRepository,
        CheckoutSession $checkoutSession
    ) {
        $this->request = $request;
        $this->logger = $logger;
        $this->scopeConfig = $scopeConfig;
        $this->cache = $cache;
        $this->lockManager = $lockManager;
        $this->remoteAddress = $remoteAddress;
        $this->cartRepository = $cartRepository;
        $this->checkoutSession = $checkoutSession;
    }

    /**
     * Generate a signed security token bound to a cart.
     *
     * Token format: timestamp:quote_id:signature
     *
     * @param int|null $quoteId Cart to bind; defaults to the current checkout-session quote.
     * @return string
     * @throws \RuntimeException When no app_key is configured or there is no cart to bind to.
     */
    public function generateSecurityToken(?int $quoteId = null): string
    {
        $secretKey = $this->getSecretKey();

        $quoteId = $quoteId ?? $this->getSessionQuoteId();
        if (!$quoteId) {
            // Expected outside checkout (e.g. config rendered by a 3DS endpoint or order
            // placement); the caller falls back to an untokenized URL, so no log noise here.
            throw new \RuntimeException('3DS Security: No active cart to bind the security token to');
        }

        $timestamp = time();
        $signature = hash_hmac('sha256', $this->signedData($timestamp, $quoteId), $secretKey);

        return $timestamp . ':' . $quoteId . ':' . $signature;
    }

    /**
     * Validate the security token from the request.
     *
     * @param string $scope Per-endpoint scope for the token-use counter (see SCOPE_*).
     * @return array{valid: bool, error: mixed, quote?: CartInterface}
     *         On success 'quote' holds the verified cart the 3DS call must be performed for.
     */
    public function validateSecurityToken(string $scope = ''): array
    {
        $token = $this->request->getParam('gp3ds_token');

        if (empty($token)) {
            return [
                'valid' => false,
                'error' => __('Security token missing. Please refresh the page and try again.')
            ];
        }

        // Parse token format: timestamp:quote_id:signature. Only the canonical form we mint is
        // accepted (no leading zeros, signs or whitespace; lowercase hex signature), so one signed
        // token cannot be re-encoded into variants that would each get a fresh usage budget.
        $parts = explode(':', (string) $token);
        if (count($parts) !== 3
            || !preg_match('/^[1-9][0-9]*$/', $parts[0])
            || !preg_match('/^[1-9][0-9]*$/', $parts[1])
            || !preg_match('/^[0-9a-f]{64}$/', $parts[2])
        ) {
            $this->logger->warning('3DS Security: Invalid token format', ['token_parts' => count($parts)]);
            return [
                'valid' => false,
                'error' => __('Invalid security token. Please refresh the page and try again.')
            ];
        }

        $timestamp = (int) $parts[0];
        $quoteId = (int) $parts[1];
        $providedSignature = $parts[2];

        // Verify the HMAC signature (proves the token, and the cart it names, were issued by us)
        $expectedSignature = hash_hmac('sha256', $this->signedData($timestamp, $quoteId), $this->getSecretKey());
        if (!hash_equals($expectedSignature, $providedSignature)) {
            $this->logger->warning('3DS Security: Invalid signature', ['ip' => $this->maskedIp()]);
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

        // The signed cart must still be a live checkout: active (not yet ordered) and non-empty.
        $quote = $this->loadEligibleQuote($quoteId, $this->tokenReference($token));
        if ($quote === null) {
            return [
                'valid' => false,
                'error' => __('Your cart could not be verified. Please refresh the page and try again.')
            ];
        }

        // Token usage limit (atomic), scoped per endpoint so both legs keep their own budget. Keyed
        // on the parsed fields rather than the raw request text.
        $usageKey = self::CACHE_PREFIX_TOKEN
            . hash('sha256', $timestamp . ':' . $quoteId . ':' . $providedSignature . '|' . $scope);
        $remainingTtl = max(1, self::TOKEN_EXPIRY_SECONDS - $tokenAge);
        $usageResult = $this->checkAndIncrementCounter($usageKey, self::MAX_TOKEN_USES, $remainingTtl);

        if ($usageResult['exceeded']) {
            $this->logger->warning('3DS Security: Token usage exhausted', [
                'uses' => $usageResult['count'],
                'scope' => $scope,
                'token_ref' => $this->tokenReference($token),
                'quote_id' => $quoteId,
                'ip' => $this->maskedIp()
            ]);
            return [
                'valid' => false,
                'error' => __('Security token exhausted. Please refresh the page and try again.')
            ];
        }

        // Rate/hourly limits are counted per cart. A cart is never shared between shoppers and
        // cannot be forged (it is inside the signed token), so these buckets need no client
        // identity: CDN edges, forwarded headers and cookie-less requests are all irrelevant.
        $cartKey = hash('sha256', 'quote|' . $quoteId);

        $rateLimitResult = $this->checkAndIncrementCounter(
            self::CACHE_PREFIX_RATE . $cartKey,
            self::RATE_LIMIT_PER_MINUTE,
            60
        );
        if ($rateLimitResult['exceeded']) {
            $this->logger->warning('3DS Security: Rate limit exceeded', [
                'quote_id' => $quoteId,
                'count' => $rateLimitResult['count'],
                'ip' => $this->maskedIp()
            ]);
            return [
                'valid' => false,
                'error' => __('Too many requests. Please wait a moment before trying again.')
            ];
        }

        $hourlyResult = $this->checkAndIncrementCounter(
            self::CACHE_PREFIX_HOURLY . $cartKey,
            self::HOURLY_LIMIT,
            3600
        );
        if ($hourlyResult['exceeded']) {
            $this->logger->warning('3DS Security: Hourly limit exceeded', [
                'quote_id' => $quoteId,
                'count' => $hourlyResult['count'],
                'ip' => $this->maskedIp()
            ]);
            return [
                'valid' => false,
                'error' => __('Request limit reached. Please try again later.')
            ];
        }

        return ['valid' => true, 'error' => null, 'quote' => $quote];
    }

    /**
     * Load the signed cart if it is still an eligible, live checkout.
     *
     * @param int $quoteId
     * @param string $tokenRef
     * @return CartInterface|null
     */
    private function loadEligibleQuote(int $quoteId, string $tokenRef): ?CartInterface
    {
        try {
            $quote = $this->cartRepository->getActive($quoteId);
        } catch (NoSuchEntityException $e) {
            $this->logger->warning('3DS Security: Cart not active or not found', [
                'quote_id' => $quoteId,
                'token_ref' => $tokenRef,
                'ip' => $this->maskedIp()
            ]);
            return null;
        }

        if (!$quote->getIsActive() || (int) $quote->getItemsCount() < 1) {
            $this->logger->warning('3DS Security: Cart not eligible for 3DS', [
                'quote_id' => $quoteId,
                'token_ref' => $tokenRef,
                'ip' => $this->maskedIp()
            ]);
            return null;
        }

        return $quote;
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
     * Build the HMAC-signed payload for a token.
     *
     * @param int $timestamp
     * @param int $quoteId
     * @return string
     */
    private function signedData(int $timestamp, int $quoteId): string
    {
        return self::SIGNED_DATA_PREFIX . $timestamp . '_' . $quoteId;
    }

    /**
     * Current checkout-session quote id, or null when there is no checkout cart.
     *
     * @return int|null
     */
    private function getSessionQuoteId(): ?int
    {
        try {
            $quoteId = (int) $this->checkoutSession->getQuoteId();
        } catch (\Throwable $e) {
            return null;
        }

        return $quoteId > 0 ? $quoteId : null;
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
     * Derive a short, non-reversible reference for a security token.
     *
     * Used only for log correlation: the same token always yields the same reference, so the
     * two validation legs that share a token can be tied together, and a failing log line can be
     * matched to the full `gp3ds_token` in web-server access logs — without logging the token.
     *
     * @param string $token
     * @return string
     */
    private function tokenReference(string $token): string
    {
        return substr(hash('sha256', $token), 0, 12);
    }

    /**
     * Masked client IP for diagnostic log context only.
     *
     * @return string
     */
    private function maskedIp(): string
    {
        $ip = (string) $this->remoteAddress->getRemoteAddress();

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
