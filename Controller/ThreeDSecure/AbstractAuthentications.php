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
     */
    public function __construct(
        Context $context,
        CorsConfigurationInterface $corsConfiguration,
        PaymentTokenManagementInterface $tokenManagement,
        Quote $quote,
        ConfigProvider $configProvider,
        GatewayConfigHelper $configHelper,
        Logger $logger,
        SessionManagerInterface $sessionManager
    ) {
        parent::__construct($context);
        $this->corsConfiguration = $corsConfiguration;
        $this->tokenManagement = $tokenManagement;
        $this->quote = $quote;
        $this->configProvider = $configProvider;
        $this->configHelper = $configHelper;
        $this->logger = $logger;
        $this->sessionManager = $sessionManager;
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
            $quote = $this->quote->loadByIdWithoutStore($requestData->quoteId);
            if (empty($quote)) {
                throw new \Exception(__('Not enough data to perform 3DS. Unable to retrieve token.'));
            }

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
        header('Set-Cookie: PHPSESSID= ' . $this->sessionManager->getSessionId() . '; SameSite=None; Secure');
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
