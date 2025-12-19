<?php

namespace GlobalPayments\PaymentGateway\Gateway\Http\Client\HostedPaymentPages;

use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\TransferInterface;

/**
 * HPP Completion Client
 * 
 * This client is used for order completion after successful HPP payment.
 * Since the payment was already processed on the hosted page, this client
 * simply returns a successful response without making any payment requests.
 */
class CompletionClient implements ClientInterface
{
    public const SUCCESS = 1;

    /**
     * construsctor
     */
    public function __construct()
    {
        //constructor
    }

    /**
     * Places request to gateway. Returns result as ENV array.
     * 
     * For HPP, the payment was already processed on the hosted page,
     * so we just return a successful response.
     *
     * @param TransferInterface $transferObject
     * @return array
     */
    public function placeRequest(TransferInterface $transferObject)
    {
        $transactionData = $transferObject->getBody();

        // Return successful response since payment was already processed on HPP
        $response = [
            'RESULT_CODE' => self::SUCCESS,
            'TXN_ID' => $transactionData['HPP_TXN_ID'] ?? null,
            'AUTH_TXN_ID' => $transactionData['HPP_TXN_ID'] ?? null,
            'AUTH_CODE' => $transactionData['HPP_AUTH_CODE'] ?? null,
            'TXN_TYPE' => $transactionData['TXN_TYPE'] ?? 'authorization',
            'GATEWAY_PROVIDER' => 'globalpayments_hpp',
            'TOKEN_RESPONSE' => $transactionData['TOKEN_RESPONSE'] ?? '',
            'ORDER_ID' => $transactionData['ORDER_ID'] ?? null,
            'MULTI_USE_TOKEN' => $transactionData['MULTI_USE_TOKEN'] ?? null
        ];

        return $response;
    }
}