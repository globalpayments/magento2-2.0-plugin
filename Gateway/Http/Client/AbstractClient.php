<?php

namespace GlobalPayments\PaymentGateway\Gateway\Http\Client;

use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Model\Method\Logger;
use GlobalPayments\Api\Entities\Transaction;
use GlobalPayments\PaymentGateway\Helper\Utils;
use GlobalPayments\PaymentGateway\Model\Helper\GatewayConfigHelper;

abstract class AbstractClient implements ClientInterface
{
    /**
     * @var GatewayConfigHelper
     */
    protected $configHelper;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var Utils
     */
    protected $utils;

    /**
     * AbstractClient constructor.
     *
     * @param GatewayConfigHelper $configHelper
     * @param Logger $logger
     * @param Utils $utils
     */
    public function __construct(
        GatewayConfigHelper $configHelper,
        Logger $logger,
        Utils $utils
    ) {
        $this->configHelper = $configHelper;
        $this->logger = $logger;
        $this->utils = $utils;
    }

    /**
     * Validate the gateway response.
     *
     * @param Transaction $gatewayResponse
     * @return bool
     */
    protected function validateGatewayResponse($gatewayResponse)
    {
        if ($gatewayResponse->responseCode !== '00' && $gatewayResponse->responseCode !== 'SUCCESS'
            || $gatewayResponse->responseMessage === 'Partially Approved'
        ) {
            if ($gatewayResponse->responseCode === '10'
                || $gatewayResponse->responseMessage === 'Partially Approved'
            ) {
                try {
                    $gatewayResponse->void()->withDescription('POST_AUTH_USER_DECLINE')->execute();
                } catch (\Exception $e) {
                    /** om nom */
                }
            }

            return false;
        }
        return true;
    }

    /**
     * Get the gateway response.
     *
     * @param Transaction $gatewayResponse
     * @return array
     */
    protected function getGatewayResponse($gatewayResponse)
    {
        $response = [];

        $response['AUTH_TXN_ID'] = $gatewayResponse->transactionId;
        $response['ORDER_ID'] = $gatewayResponse->orderId;
        $response['AUTH_CODE'] = $gatewayResponse->authorizationCode;
        $response['RESULT_CODE'] = $gatewayResponse->responseCode;
        $response['TXN_ID'] = $gatewayResponse->transactionId;

        return $response;
    }
}
