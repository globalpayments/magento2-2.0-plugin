<?php

namespace GlobalPayments\PaymentGateway\Gateway\Http\Client;

use Magento\Payment\Gateway\Http\ClientException;
use Magento\Payment\Gateway\Http\TransferInterface;
use GlobalPayments\Api\Services\ReportingService;
use GlobalPayments\Api\Entities\Exceptions\ApiException;

class GetTransactionDetailsClient extends AbstractClient
{
    /**
     * @inheritDoc
     */
    public function placeRequest(TransferInterface $transferObject)
    {
        $transactionData = $transferObject->getBody();
        $response = [];

        try {
            $this->configHelper->setUpConfig($transactionData['SERVICES_CONFIG']);

            $gatewayResponse = ReportingService::transactionDetail($transactionData['TRANSACTION_ID'])->execute();

            $response['AMOUNT'] = $gatewayResponse->amount;
            $response['ALTERNATIVE_PAYMENT_RESPONSE'] = $gatewayResponse->alternativePaymentResponse ?? null;
            $response['BNPL_PROVIDER'] = $gatewayResponse->bnplResponse->providerName ?? null;
            $response['CURRENCY'] = $gatewayResponse->currency;
            $response['ORDER_ID'] = $gatewayResponse->orderId ?? null;
            $response['PAYMENT_TYPE'] = $gatewayResponse->paymentType ?? null;
            $response['TRANSACTION_ID'] = $gatewayResponse->transactionId;
            $response['TRANSACTION_STATUS'] = $gatewayResponse->transactionStatus;
            $response['TRANSACTION_TYPE'] = $gatewayResponse->transactionType;

            return $response;
        } catch (ApiException $e) {
            $message = __($e->getMessage() ?: 'Sorry, but something went wrong');
            $this->logger->debug([$message]);
            throw new ClientException($message);
        }
    }
}
