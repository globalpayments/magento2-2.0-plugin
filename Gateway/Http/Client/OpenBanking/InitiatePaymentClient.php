<?php

namespace GlobalPayments\PaymentGateway\Gateway\Http\Client\OpenBanking;

use GlobalPayments\Api\Entities\Enums\BankPaymentType;
use GlobalPayments\Api\Entities\Exceptions\ApiException;
use GlobalPayments\Api\Gateways\OpenBankingProvider;
use GlobalPayments\Api\PaymentMethods\BankPayment;
use GlobalPayments\PaymentGateway\Gateway\Http\Client\AbstractClient;
use Magento\Payment\Gateway\Http\ClientException;
use Magento\Payment\Gateway\Http\TransferInterface;

class InitiatePaymentClient extends AbstractClient
{
    /**
     * @inheritDoc
     */
    public function placeRequest(TransferInterface $transferObject)
    {
        $transactionData = $transferObject->getBody();
        $config = $transactionData['CONFIG'];

        $paymentMethod = new BankPayment();
        $paymentMethod->returnUrl = $transactionData['PROVIDER_DATA']['returnUrl'];
        $paymentMethod->statusUpdateUrl = $transactionData['PROVIDER_DATA']['statusUrl'];

        $provider = OpenBankingProvider::getBankPaymentType($transactionData['CURRENCY']);
        $paymentMethodData = [
            'accountName' => $config->getValue('account_name'),
            'accountNumber' => $config->getValue('account_number'),
            'iban' => $config->getValue('iban'),
            'sortCode' => $provider === BankPaymentType::FASTERPAYMENTS ? $config->getValue('sort_code') : '',
        ];

        foreach ($paymentMethodData as $key => $value) {
            if (isset($value) && property_exists($paymentMethod, $key)) {
                $paymentMethod->{$key} = $value;
            }
        }

        $countries = $config->getAvailableCountries();
        if (!empty($countries)) {
            $paymentMethod->countries = $countries;
        }

        try {
            $this->configHelper->setUpConfig($transactionData['SERVICES_CONFIG']);
            $remittanceReference = $transactionData['REMITTANCE_REFERENCE'];
            $gatewayResponse = $paymentMethod->charge($transactionData['AMOUNT'])
                ->withCurrency($transactionData['CURRENCY'])
                ->withOrderId($transactionData['ORDER_ID'])
                ->withRemittanceReference($remittanceReference['type'], $remittanceReference['value'])
                ->execute();

            if (!$this->validateGatewayResponse($gatewayResponse)) {
                throw new ApiException($this->utils->mapResponseCodeToFriendlyMessage($gatewayResponse->responseCode));
            }

            $response = $this->getGatewayResponse($gatewayResponse);
            $response['REDIRECT_URL'] = $gatewayResponse->bankPaymentResponse->redirectUrl;
            $response['TXN_TYPE'] = $transactionData['TXN_TYPE'];
            $response['GATEWAY_PROVIDER'] = $transactionData['SERVICES_CONFIG']['gatewayMethodCode'];

            return $response;
        } catch (ApiException $e) {
            $message = __($e->getMessage() ?: 'Sorry, but something went wrong');
            $this->logger->debug([$message]);
            throw new ClientException($message);
        }
    }
}
