<?php

namespace GlobalPayments\PaymentGateway\Observer;

use GlobalPayments\PaymentGateway\Gateway\Config;
use Magento\Framework\Event\Observer;
use Magento\Payment\Observer\AbstractDataAssignObserver;
use Magento\Quote\Api\Data\PaymentInterface;

class DataAssignObserver extends AbstractDataAssignObserver
{
    /**
     * @inheritDoc
     */
    public function execute(Observer $observer)
    {
        $data = $this->readDataArgument($observer);
        $additionalData = $data->getData(PaymentInterface::KEY_ADDITIONAL_DATA);
        $paymentInfo = $this->readPaymentModelArgument($observer);

        if (isset($additionalData['public_hash'])) {
            $paymentInfo->setAdditionalInformation(
                'public_hash',
                $additionalData['public_hash']
            );
        }
        if (isset($additionalData['tokenResponse'])) {
            $paymentInfo->setAdditionalInformation(
                'tokenResponse',
                json_decode($additionalData['tokenResponse'], true)
            );
        }
        if (isset($additionalData['dw_token'])) {
            $paymentInfo->setAdditionalInformation(
                'dw_token',
                isset($additionalData['gatewayMethodCode']) && $additionalData['gatewayMethodCode'] == Config::CODE_HEARTLAND ? $additionalData['dw_token'] : str_replace('\\\\', '\\', $additionalData['dw_token'])
            );
        }
        if (isset($additionalData['payerInfo'])) {
            $paymentInfo->setAdditionalInformation(
                'payerInfo',
                json_decode($additionalData['payerInfo'], true)
            );
        }
        if (isset($additionalData['serverTransId'])) {
            $paymentInfo->setAdditionalInformation(
                'serverTransId',
                $additionalData['serverTransId']
            );
        }
        if (isset($additionalData['giftcardNumber'])) {
            $paymentInfo->setAdditionalInformation(
                'giftcardNumber',
                json_decode($additionalData['giftcardNumber'], true)
            );
        }
        if (isset($additionalData['giftcardPin'])) {
            $paymentInfo->setAdditionalInformation(
                'giftcardPin',
                json_decode($additionalData['giftcardPin'], true)
            );
        }
        if (isset($additionalData['entryMode'])) {
            $paymentInfo->setAdditionalInformation(
                'entryMode',
                $additionalData['entryMode']
            );
        }
        if (isset($additionalData['diuiApmPayment'])) {
            $paymentInfo->setAdditionalInformation(
                'diuiApmPayment',
                $additionalData['diuiApmPayment']
            );
        }
    }
}
