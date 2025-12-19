<?php

namespace GlobalPayments\PaymentGateway\Controller\ThreeDSecure;

class ChallengeNotification extends AbstractAuthentications
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

        $response = [];
        try {
            $CRes = $this->getRequest()->getParam('cres');
            if (!empty($CRes)) {
                $convertedCRes = json_decode(base64_decode($CRes));
                $response = [
                    'threeDSServerTransID' => $convertedCRes->threeDSServerTransID,
                    'transStatus' => $convertedCRes->transStatus ?? '',
                ];
            }
        } catch (\Exception $e) {
            $response = [
                'error' => true,
                'message' => $e->getMessage(),
            ];
        }
        $responseJson = json_encode($response, JSON_UNESCAPED_SLASHES);
        $script = <<<EOT
<!DOCTYPE html>
<html>
<body>
<script>
    if (window.parent !== window) {
        window.parent.postMessage({ data: $responseJson, event: 'challengeNotification' }, window.location.origin);
    }
</script>
</body>
</html>
EOT;
        $this->sendResponse($script, 'text/html');
    }
}
