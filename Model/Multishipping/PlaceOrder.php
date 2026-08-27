<?php

declare(strict_types=1);

namespace GlobalPayments\PaymentGateway\Model\Multishipping;

use Magento\Multishipping\Model\Checkout\Type\Multishipping\PlaceOrderInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderManagementInterface;
use Psr\Log\LoggerInterface;

class PlaceOrder implements PlaceOrderInterface
{
    /**
     * @var OrderManagementInterface
     */
    private $orderManagement;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param OrderManagementInterface $orderManagement
     * @param LoggerInterface $logger
     */
    public function __construct(
        OrderManagementInterface $orderManagement,
        LoggerInterface          $logger
    )
    {
        $this->orderManagement = $orderManagement;
        $this->logger = $logger;
    }

    /**
     * @inheritdoc
     */
    public function place(array $orderList): array
    {
        $errorList = [];
        $multiUseToken = null;
        $tokenDetails = null;

        foreach ($orderList as $order) {
            $incrementId = $order->getIncrementId();

            try {
                if ($multiUseToken !== null) {
                    $this->updatePaymentWithMultiUseToken($order, $multiUseToken, $tokenDetails);
                }

                $this->orderManagement->place($order);

                if ($multiUseToken === null) {
                    $extracted = $this->extractMultiUseToken($order);
                    if ($extracted !== null) {
                        $multiUseToken = $extracted['token'];
                        $tokenDetails = $extracted['details'];
                    } else {
                        $this->logger->warning(
                            "GP Multishipping PlaceOrder: Could not extract multi-use token "
                            . "from order {$incrementId}. Subsequent orders may not be charged."
                        );
                    }
                }
            } catch (\Exception $e) {
                $errorList[$incrementId] = $e;
                $this->logger->critical(
                    "GP Multishipping PlaceOrder: Order {$incrementId} placement failed: "
                    . $e->getMessage(),
                    ['exception' => $e]
                );
            }
        }

        return $errorList;
    }

    /**
     * Extract the multi-use token from a successfully placed order's payment.
     *
     * @param OrderInterface $order
     * @return array|null ['token' => string, 'details' => array]
     */
    private function extractMultiUseToken(OrderInterface $order): ?array
    {
        $payment = $order->getPayment();
        if ($payment === null) {
            return null;
        }

        $additionalInfo = $payment->getAdditionalInformation();
        $tokenResponse = $additionalInfo['tokenResponse'] ?? [];
        if (is_string($tokenResponse)) {
            $tokenResponse = json_decode($tokenResponse, true) ?: [];
        }
        $details = $tokenResponse['details'] ?? [];

        $extensionAttributes = $payment->getExtensionAttributes();
        if ($extensionAttributes !== null) {
            $vaultToken = $extensionAttributes->getVaultPaymentToken();
            if ($vaultToken !== null && $vaultToken->getGatewayToken()) {
                return [
                    'token' => $vaultToken->getGatewayToken(),
                    'details' => $details,
                ];
            }
        }

        return null;
    }

    /**
     * Update an order's payment data to use the multi-use token
     *
     * @param OrderInterface $order
     * @param string $multiUseToken
     * @param array $originalDetails
     * @return void
     */
    private function updatePaymentWithMultiUseToken(
        OrderInterface $order,
        string         $multiUseToken,
        array          $originalDetails
    ): void
    {
        $payment = $order->getPayment();
        if ($payment === null) {
            return;
        }

        $additionalInfo = $payment->getAdditionalInformation();
        $tokenResponse = $additionalInfo['tokenResponse'] ?? [];
        if (is_string($tokenResponse)) {
            $tokenResponse = json_decode($tokenResponse, true) ?: [];
        }

        $tokenResponse['paymentReference'] = $multiUseToken;

        if (empty($tokenResponse['details'])) {
            $tokenResponse['details'] = $originalDetails;
        }
        $tokenResponse['details']['useStoredCard'] = true;

        $payment->setAdditionalInformation('tokenResponse', $tokenResponse);
        $payment->setAdditionalInformation('is_active_payment_token_enabler', true);
        $payment->unsAdditionalInformation('serverTransId');
    }
}
