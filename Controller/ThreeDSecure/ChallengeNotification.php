<?php

namespace GlobalPayments\PaymentGateway\Controller\ThreeDSecure;

class ChallengeNotification extends AbstractAuthentications
{
    /**
     * Valid EMV 3DS transStatus values.
     */
    private const VALID_TRANS_STATUSES = ['Y', 'N', 'U', 'A', 'C', 'D', 'R', 'I', 'S'];

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

        $response = [];
        $cRes = $this->getRequest()->getParam('cres');
        if (!empty($cRes)) {
            $convertedCRes = $this->decodeNotificationPayload($cRes);
            $transId = $convertedCRes['threeDSServerTransID'] ?? null;
            $transStatus = $convertedCRes['transStatus'] ?? null;

            if (
                $this->isValidThreeDSServerTransId($transId)
                && in_array($transStatus, self::VALID_TRANS_STATUSES, true)
            ) {
                $response = [
                    'threeDSServerTransID' => $transId,
                    'transStatus' => $transStatus,
                ];
            } else {
                $response = $this->getInvalidNotificationResponse();
            }
        }

        $this->sendNotificationResponse('challengeNotification', $response);
    }
}