<?php

namespace GlobalPayments\PaymentGateway\Model;

use Magento\Payment\Gateway\Http\ClientException;
use Magento\Payment\Gateway\Http\ConverterException;
use GlobalPayments\PaymentGateway\Gateway\Config;
use GlobalPayments\PaymentGateway\Gateway\Http\Client\GetTransactionDetailsClient as Client;
use GlobalPayments\PaymentGateway\Gateway\Http\TransferFactory;
use GlobalPayments\PaymentGateway\Gateway\Request\GetTransactionDetailsRequest as Request;

class TransactionInfo
{
    /**
     * @var Client
     */
    private $client;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var Request
     */
    private $request;

    /**
     * @var TransferFactory
     */
    private $transferFactory;

    /**
     * TransactionInfo constructor.
     *
     * @param Client $client
     * @param Config $config
     * @param Request $request
     * @param TransferFactory $transferFactory
     */
    public function __construct(
        Client $client,
        Config $config,
        Request $request,
        TransferFactory $transferFactory
    ) {
        $this->client = $client;
        $this->config = $config;
        $this->request = $request;
        $this->transferFactory = $transferFactory;
    }

    /**
     * Get transaction details by transaction id.
     *
     * @param string $txnId
     * @return array
     * @throws ClientException
     * @throws ConverterException
     */
    public function getTransactionDetailsByTxnId($txnId)
    {
        $additionalData = [
            'transactionId' => $txnId,
            'servicesConfig' => $this->config->getBackendGatewayOptions()
        ];

        $request = $this->request->build(['additionalData' => $additionalData]);

        return $this->client->placeRequest($this->transferFactory->create($request));
    }
}
