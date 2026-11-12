<?php

namespace GlobalPayments\PaymentGateway\Controller\ThreeDSecure;

class MethodNotification extends AbstractAuthentications
{
    /**
     * @inheritDoc
     */
    public function execute()
    {
        if ($this->getRequest()->getServer('REQUEST_METHOD') !== 'POST') {
            return;
        }
        if ($this->getRequest()->getServer('CONTENT_TYPE') !== 'application/x-www-form-urlencoded') {
            return;
        }

        $this->setNotificationEndpointCookies();

        $convertedThreeDSMethodData = $this->decodeNotificationPayload(
            $this->getRequest()->getParam('threeDSMethodData')
        );
        $transId = $convertedThreeDSMethodData['threeDSServerTransID'] ?? null;

        $response = $this->isValidThreeDSServerTransId($transId)
            ? ['threeDSServerTransID' => $transId]
            : $this->getInvalidNotificationResponse();

        $this->sendNotificationResponse('methodNotification', $response);
    }
}