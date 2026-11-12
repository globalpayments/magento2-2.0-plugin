<?php

namespace GlobalPayments\PaymentGateway\Controller\ThreeDSecure;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Payment\Model\Method\Logger;
use Magento\Quote\Model\Quote;
use Magento\Vault\Api\PaymentTokenManagementInterface;
use GlobalPayments\PaymentGateway\Configuration\CorsConfigurationInterface;
use GlobalPayments\PaymentGateway\Configuration\CorsConfiguration;
use GlobalPayments\PaymentGateway\Helper\ThreeDSecureSecurity;
use GlobalPayments\PaymentGateway\Model\Helper\GatewayConfigHelper;
use GlobalPayments\PaymentGateway\Model\Ui\ConfigProvider;

abstract class AbstractAuthentications extends Action implements CsrfAwareActionInterface
{
    public const YES = 'YES';

    /**
     * @var ConfigProvider
     */
    protected $configProvider;

    /**
     * @var GatewayConfigHelper
     */
    protected $configHelper;

    /**
     * @var CorsConfigurationInterface
     */
    protected $corsConfiguration;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var SessionManagerInterface
     */
    protected $sessionManager;

    /**
     * @var PaymentTokenManagementInterface
     */
    protected $tokenManagement;

    /**
     * @var Quote
     */
    protected $quote;

    /**
     * @var ThreeDSecureSecurity
     */
    protected $threeDSecureSecurity;

    /**
     * Cart verified by the 3DS security token for the current request.
     *
     * @var \Magento\Quote\Model\Quote|null
     */
    private $validatedQuote = null;

    /**
     * AbstractAuthentications constructor.
     *
     * @param Context $context
     * @param CorsConfigurationInterface $corsConfiguration
     * @param PaymentTokenManagementInterface $tokenManagement
     * @param Quote $quote
     * @param ConfigProvider $configProvider
     * @param GatewayConfigHelper $configHelper
     * @param Logger $logger
     * @param SessionManagerInterface $sessionManager
     * @param ThreeDSecureSecurity $threeDSecureSecurity
     */
    public function __construct(
        Context $context,
        CorsConfigurationInterface $corsConfiguration,
        PaymentTokenManagementInterface $tokenManagement,
        Quote $quote,
        ConfigProvider $configProvider,
        GatewayConfigHelper $configHelper,
        Logger $logger,
        SessionManagerInterface $sessionManager,
        ThreeDSecureSecurity $threeDSecureSecurity
    ) {
        parent::__construct($context);
        $this->corsConfiguration = $corsConfiguration;
        $this->tokenManagement = $tokenManagement;
        $this->quote = $quote;
        $this->configProvider = $configProvider;
        $this->configHelper = $configHelper;
        $this->logger = $logger;
        $this->sessionManager = $sessionManager;
        $this->threeDSecureSecurity = $threeDSecureSecurity;
    }

    /**
     * Validate the 3DS security token to prevent carding attacks.
     *
     * On success the verified cart is kept so the 3DS call is performed for that cart's real
     * amount/currency/customer rather than values supplied by the client.
     *
     * @param string $scope Per-endpoint token-use scope (see ThreeDSecureSecurity::SCOPE_*),
     *                      so each 3DS endpoint keeps its own token reuse budget.
     * @return array ['valid' => bool, 'error' => string|null]
     */
    protected function validateSecurityToken(string $scope = ''): array
    {
        $result = $this->threeDSecureSecurity->validateSecurityToken($scope);
        $this->validatedQuote = $result['valid'] ? ($result['quote'] ?? null) : null;

        return $result;
    }

    /**
     * Base grand total of the verified cart.
     *
     * @return float
     * @throws \Exception
     */
    protected function getValidatedAmount(): float
    {
        return (float) $this->requireValidatedQuote()->getBaseGrandTotal();
    }

    /**
     * Base currency code of the verified cart.
     *
     * @return string
     * @throws \Exception
     */
    protected function getValidatedCurrency(): string
    {
        return (string) $this->requireValidatedQuote()->getBaseCurrencyCode();
    }

    /**
     * The cart verified by validateSecurityToken().
     *
     * @return \Magento\Quote\Model\Quote
     * @throws \Exception
     */
    private function requireValidatedQuote(): Quote
    {
        if ($this->validatedQuote === null) {
            throw new \Exception(__('Not enough data to perform 3DS. Unable to retrieve cart.'));
        }

        return $this->validatedQuote;
    }

    /**
     * Send security error response.
     *
     * @param string|\Magento\Framework\Phrase $errorMessage
     * @return void
     */
    protected function sendSecurityErrorResponse($errorMessage): void
    {
        $response = [
            'error' => true,
            'message' => (string)$errorMessage,
            'enrolled' => 'SECURITY_ERROR',
        ];
        $this->sendResponse(json_encode($response));
    }

    /**
     * Get the payment token.
     *
     * @param \stdClass $requestData
     * @return string
     * @throws \Exception
     */
    protected function getToken($requestData)
    {
        if (empty($requestData->vaultToken) && empty($requestData->tokenResponse)) {
            throw new \Exception(__('Not enough data to perform 3DS. Unable to retrieve token.'));
        }

        if (isset($requestData->vaultToken)) {
            // Use the cart from the signed token, not the client-supplied quoteId, so a saved card
            // can only be resolved for the customer who owns the verified cart.
            $quote = $this->requireValidatedQuote();

            return $this->tokenManagement->getByPublicHash($requestData->vaultToken, $quote->getCustomerId())
                ->getGatewayToken();
        }

        $tokenResponse = json_decode($requestData->tokenResponse);
        if (empty($tokenResponse->paymentReference)) {
            throw new \Exception(__('Not enough data to perform 3DS. Unable to retrieve token.'));
        }

        return $tokenResponse->paymentReference;
    }

    /**
     * Send the response back to the client.
     *
     * @param string $response
     * @param string $contentType
     */
    protected function sendResponse($response, $contentType = 'application/json')
    {
        $this->getResponse()->setHeader('Content-type', $contentType);
        if ($this->corsConfiguration->canSetHeaders()) {
            $this->setCorsHeaders();
        }
        $this->getResponse()->setBody($response);
        $this->getResponse()->sendResponse();
    }

    /**
     * Set the CORS headers if there were added in the config file.
     */
    protected function setCorsHeaders()
    {
        if (!empty($this->corsConfiguration->getAllowedOrigins())) {
            $this->getResponse()->setHeader(
                CorsConfiguration::CORS_ALLOWED_ORIGINS,
                $this->corsConfiguration->getAllowedOrigins()
            );
        }
        if (!empty($this->corsConfiguration->getAllowedHeaders())) {
            $this->getResponse()->setHeader(
                CorsConfiguration::CORS_ALLOWED_HEADERS,
                $this->corsConfiguration->getAllowedHeaders()
            );
        }
        if (!empty($this->corsConfiguration->getAllowedMethods())) {
            $this->getResponse()->setHeader(
                CorsConfiguration::CORS_ALLOWED_METHODS,
                $this->corsConfiguration->getAllowedMethods()
            );
        }
        if (!empty($this->corsConfiguration->getMaxAge())) {
            $this->getResponse()->setHeader(
                CorsConfiguration::CORS_MAX_AGE,
                $this->corsConfiguration->getMaxAge()
            );
        }
        if (!empty($this->corsConfiguration->getAllowCredentials())) {
            $this->getResponse()->setHeader(
                CorsConfiguration::CORS_ALLOW_CREDENTIALS,
                $this->corsConfiguration->getAllowCredentials()
            );
        }
        if (!empty($this->corsConfiguration->getExposedHeaders())) {
            $this->getResponse()->setHeader(
                CorsConfiguration::CORS_EXPOSED_HEADERS,
                $this->corsConfiguration->getExposedHeaders()
            );
        }
    }

    /**
     * Set the cookies for the notification endpoints, in order to avoid random disconnects.
     *
     * @return void
     */
    protected function setNotificationEndpointCookies()
    {
        header(
            'Set-Cookie: PHPSESSID=' . $this->sessionManager->getSessionId() . '; SameSite=None; Secure; HttpOnly'
        );
    }

    /**
     * Decode a base64/base64url-encoded JSON 3DS notification payload (CRes / threeDSMethodData).
     *
     * @param mixed $encoded
     * @return array|null Decoded object as an array, or null if it is not valid JSON.
     */
    protected function decodeNotificationPayload($encoded): ?array
    {
        if (!is_string($encoded) || $encoded === '') {
            return null;
        }

        // EMV 3DS uses base64url without padding; normalise to standard base64 before decoding.
        $decoded = base64_decode(strtr($encoded, '-_', '+/'));
        $data = is_string($decoded) ? json_decode($decoded, true) : null;

        return is_array($data) ? $data : null;
    }

    /**
     * Whether the value is a valid EMV 3DS threeDSServerTransID.
     *
     * @param mixed $value
     * @return bool
     */
    protected function isValidThreeDSServerTransId($value): bool
    {
        return is_string($value)
            && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value) === 1;
    }

    /**
     * Handle error payload for notifications that fail validation.
     *
     * @return array
     */
    protected function getInvalidNotificationResponse(): array
    {
        return [
            'error' => true,
            'message' => (string) __('Invalid 3DS notification data.'),
        ];
    }

    /**
     * Render the HTML page that posts the notification result to the parent (checkout) window.
     *
     * The payload is embedded in an inline script, so it must be encoded to be safe in an HTML
     * context: JSON_HEX_* prevents "</script>" and quote characters from breaking out.
     *
     * @param string $event
     * @param array $response
     * @return void
     */
    protected function sendNotificationResponse(string $event, array $response): void
    {
        $flags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
        $responseJson = json_encode($response, $flags);
        $eventJson = json_encode($event, $flags);

        $html = <<<EOT
<!DOCTYPE html>
<html>
<body>
<script>
    if (window.parent !== window) {
        window.parent.postMessage({ data: $responseJson, event: $eventJson }, window.location.origin);
    }
</script>
</body>
</html>
EOT;
        $this->sendResponse($html, 'text/html');
    }

    /**
     * @inheritDoc
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    /**
     * @inheritDoc
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}
