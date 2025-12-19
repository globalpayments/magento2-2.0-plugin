<?php

namespace GlobalPayments\PaymentGateway\Gateway\Command;

use GlobalPayments\PaymentGateway\Gateway\ConfigFactory;
use Magento\Framework\DataObject;
use Magento\Payment\Gateway\CommandInterface;
use Magento\Payment\Model\InfoInterface;
use Magento\Sales\Model\Order;

class ConditionalInitializeCommand implements CommandInterface
{
    /**
     * @var ConfigFactory
     */
    private $configFactory;

    /**
     * @param ConfigFactory $configFactory
     */
    public function __construct(
        ConfigFactory $configFactory
    ) {
        $this->configFactory = $configFactory;
    }

    /**
     * Get appropriate order state based on status
     *
     * @param string $status
     * @return string
     */
    private function getStateForStatus(string $status): string
    {
        // Map common statuses to their states
        $statusToStateMap = [
            'pending' => Order::STATE_NEW,
            'processing' => Order::STATE_PROCESSING,
            'complete' => Order::STATE_COMPLETE,
            'holded' => Order::STATE_HOLDED,
            'canceled' => Order::STATE_CANCELED,
            'closed' => Order::STATE_CLOSED,
            'fraud' => Order::STATE_PAYMENT_REVIEW,
        ];
        
        return $statusToStateMap[$status] ?? Order::STATE_PROCESSING;
    }

    /**
     * @inheritDoc
     */
    public function execute(array $commandSubject)
    {
        /** @var InfoInterface $payment */
        $payment = $commandSubject['payment']->getPayment();
        
        // Get the payment method configuration
        $config = $this->configFactory->create($payment->getMethod());
        
        // Check if this is a hosted payment method (HPP mode)
        $paymentMethod = $config->getValue('payment_method');
        
        // Check for HPP mode - either explicit hosted config or HPP_TRANSACTION token indicating HPP selection
        $additionalInfo = $payment->getAdditionalInformation();
        $tokenResponse = $additionalInfo['tokenResponse'] ?? null;
        
        $isHppMode = ($paymentMethod === 'hosted') || 
                     ($tokenResponse === 'HPP_TRANSACTION' && $payment->getMethod() === 'globalpayments_paymentgateway_gpApi');
        $dropInUi = ($paymentMethod === 'embedded' && $payment->getMethod() === 'globalpayments_paymentgateway_gpApi');
        // Only initialize for hosted payment method (HPP)
        // Order status and state will be set accordingly to the admin configuration for HPP methods & drop-in UI
        if ($isHppMode || $dropInUi) {            
            // Set the async payment flag
            $payment->setAdditionalInformation(InitializeCommand::IS_ASYNC_PAYMENT_METHOD, true);
            
            /** @var Order $order */
            $order = $payment->getOrder();
            $order->setCanSendNewEmailFlag(false);

            // Get configured order status from admin panel
            $methodInstance = $payment->getMethodInstance();
            $configuredStatus = $methodInstance->getConfigData('order_status');
            
            // Use configured status or default to pending_payment
            $orderStatus = $configuredStatus ?: Order::STATE_PENDING_PAYMENT;
            
            // Set appropriate state based on configured status
            $orderState = $this->getStateForStatus($orderStatus);

            /** @var DataObject $stateObject */
            $stateObject = $commandSubject['stateObject'];
            $stateObject->setState($orderState);
            $stateObject->setStatus($orderStatus);
            $stateObject->setIsNotified(false);
            
        } else {
            //Nothing
        }
        
        // For embedded payments, do nothing - let normal processing continue
    }
}