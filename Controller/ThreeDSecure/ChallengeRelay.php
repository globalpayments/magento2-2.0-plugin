<?php

namespace GlobalPayments\PaymentGateway\Controller\ThreeDSecure;

use Magento\Csp\Api\CspAwareActionInterface;
use Magento\Csp\Api\Data\PolicyInterface;
use Magento\Csp\Model\Policy\FetchPolicy;
use Magento\Framework\App\Action\{Action, Context};
use Magento\Framework\App\{CsrfAwareActionInterface, RequestInterface};
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\Encryption\EncryptorInterface;

/**
 * Challenge Relay Controller
 *
 * This controller serves as a CSP-aware intermediary between the checkout page and the ACS
 * (Access Control Server) for 3DS challenges. It dynamically adds the ACS URL to the CSP
 * headers, allowing the challenge to proceed without requiring a wildcard CSP policy.
 *
 * Flow:
 * 1. InitiateAuthentication returns an encrypted relay URL containing the ACS challenge data
 * 2. Checkout JS creates an iframe + form POST targeting this controller (same origin, no CSP issue)
 * 3. This controller decrypts the ACS URL and dynamically adds it to CSP via modifyCsp()
 * 4. It renders a page with an auto-submitting form that POSTs to the ACS
 * 5. The ACS challenge loads within this frame
 * 6. On completion, the ChallengeNotification controller sends postMessage back to checkout
 */
class ChallengeRelay extends Action implements CsrfAwareActionInterface, CspAwareActionInterface
{
    /**
     * @var EncryptorInterface
     */
    private EncryptorInterface $encryptor;

    /**
     * ACS URL extracted during execute(), used by modifyCsp() which runs after execute().
     *
     * @var string|null
     */
    private ?string $acsUrl = null;

    /**
     * @param Context $context
     * @param EncryptorInterface $encryptor
     */
    public function __construct(
        Context $context,
        EncryptorInterface $encryptor
    ) {
        parent::__construct($context);
        $this->encryptor = $encryptor;
    }

    /**
     * @inheritDoc
     */
    public function execute()
    {
        $encryptedData = $this->getRequest()->getParam('data');

        if (empty($encryptedData)) {
            $this->getResponse()->setHttpResponseCode(400);
            $this->getResponse()->setBody('Invalid challenge data.');
            return;
        }

        $decodedData = base64_decode($encryptedData, true);

        if ($decodedData === false) {
            $this->getResponse()->setHttpResponseCode(400);
            $this->getResponse()->setBody('Invalid challenge data.');
            return;
        }

        try {
            $decrypted = $this->encryptor->decrypt($decodedData);
            $challengeData = json_decode($decrypted, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->getResponse()->setHttpResponseCode(400);
                $this->getResponse()->setBody('Invalid challenge data.');
                return;
            }
        } catch (\Throwable $e) {
            $this->getResponse()->setHttpResponseCode(400);
            $this->getResponse()->setBody('Invalid challenge data.');
            return;
        }

        if (
            empty($challengeData)
            || empty($challengeData['requestUrl'])
            || empty($challengeData['encodedChallengeRequest'])
        ) {
            $this->getResponse()->setHttpResponseCode(400);
            $this->getResponse()->setBody('Invalid challenge data.');
            return;
        }

        $this->acsUrl = $challengeData['requestUrl'];

        // Validate ACS URL is HTTPS
        if (strpos($this->acsUrl, 'https://') !== 0) {
            $this->getResponse()->setHttpResponseCode(400);
            $this->getResponse()->setBody('Invalid ACS URL.');
            return;
        }

        $encodedChallengeRequest = $challengeData['encodedChallengeRequest'];
        $messageType = $challengeData['messageType'] ?? 'creq';

        // Render a minimal HTML page that auto-submits the challenge form to the ACS
        $html = $this->buildChallengeRelayHtml($this->acsUrl, $messageType, $encodedChallengeRequest);

        $this->getResponse()->setHeader('Content-Type', 'text/html');
        $this->getResponse()->setHeader('X-Frame-Options', 'SAMEORIGIN');
        $this->getResponse()->setBody($html);
    }

    /**
     * Modify CSP to dynamically allow the ACS URL for this page only.
     * This is called after execute() by the CSP rendering observer.
     *
     * @param PolicyInterface[] $appliedPolicies
     * @return PolicyInterface[]
     */
    public function modifyCsp(array $appliedPolicies): array
    {
        if (empty($this->acsUrl)) {
            return $appliedPolicies;
        }

        $acsHost = $this->extractHost($this->acsUrl);

        // Add ACS host to form-action (for the form POST to ACS)
        $appliedPolicies[] = new FetchPolicy(
            'form-action',
            false,
            [$acsHost],
            [],
            false,
            false,
            false,
            [],
            [],
            false,
            false
        );

        // Add ACS host to frame-src (in case ACS uses nested frames)
        $appliedPolicies[] = new FetchPolicy(
            'frame-src',
            false,
            [$acsHost],
            [],
            false,
            false,
            false,
            [],
            [],
            false,
            false
        );

        // Allow inline script for the auto-submit on this relay page
        $appliedPolicies[] = new FetchPolicy(
            'script-src',
            false,
            [],
            [],
            false,
            true,  // inlineAllowed
            false,
            [],
            [],
            false,
            false
        );

        return $appliedPolicies;
    }

    /**
     * Get host from URL for CSP whitelisting.
     *
     * @param string $url
     * @return string
     */
    private function extractHost(string $url): string
    {
        $parsed = parse_url($url);
        if (empty($parsed['host'])) {
            return $url;
        }

        $scheme = $parsed['scheme'] ?? 'https';
        $host = $parsed['host'];

        return $scheme . '://' . $host;
    }

    /**
     * Build the HTML page that auto-submits the challenge form.
     *
     * @param string $acsUrl
     * @param string $messageType
     * @param string $encodedChallengeRequest
     * @return string
     */
    private function buildChallengeRelayHtml(
        string $acsUrl,
        string $messageType,
        string $encodedChallengeRequest
    ): string {
        $escapedAcsUrl = htmlspecialchars($acsUrl, ENT_QUOTES, 'UTF-8');
        $escapedMessageType = htmlspecialchars($messageType, ENT_QUOTES, 'UTF-8');
        $escapedChallengeRequest = htmlspecialchars($encodedChallengeRequest, ENT_QUOTES, 'UTF-8');

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>3DS Challenge</title>
    <style>
        body { margin: 0; padding: 0; overflow: hidden; }
        iframe { border: none; width: 100%; height: 100vh; }
    </style>
</head>
<body>
    <iframe id="acsFrame" name="acsFrame"></iframe>
    <form id="challengeForm" method="POST" action="{$escapedAcsUrl}" target="acsFrame">
        <input type="hidden" name="{$escapedMessageType}" value="{$escapedChallengeRequest}" />
    </form>
    <script>
        // Auto-submit the form to the ACS within the nested iframe
        document.getElementById('challengeForm').submit();

        // Forward postMessage from nested iframe (ChallengeNotification) to parent (checkout page)
        window.addEventListener('message', function(e) {
            if (e.origin === window.location.origin && e.data && e.data.event) {
                window.parent.postMessage(e.data, window.location.origin);
            }
        });
    </script>
</body>
</html>
HTML;
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
