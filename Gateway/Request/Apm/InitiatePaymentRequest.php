<?php

namespace GlobalPayments\PaymentGateway\Gateway\Request\Apm;

use GlobalPayments\PaymentGateway\Gateway\ConfigFactory;
use GlobalPayments\PaymentGateway\Helper\Utils;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Payment\Gateway\Data\OrderAdapterInterface;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Payment\Model\MethodInterface;

class InitiatePaymentRequest implements BuilderInterface
{
    /**
     * @var ConfigFactory
     */
    private $configFactory;

    /**
     * @var Utils
     */
    private $utils;

    /**
     * 
     * @var OrderRepositoryInterface
     */
    private $orderRepository;
    
    /**
     * Initiate Payment Request constructor.
     * 
     * @param ConfigFactory $configFactory 
     * @param Utils $utils 
     * @param OrderRepositoryInterface $orderRepository 
     */
    public function __construct(
        ConfigFactory $configFactory,
        Utils $utils,
        OrderRepositoryInterface $orderRepository
    ) {
        $this->configFactory = $configFactory;
        $this->utils = $utils;
        $this->orderRepository = $orderRepository;
    }

    /**
     * @inheritDoc
     */
    public function build(array $buildSubject)
    {
        $payment = SubjectReader::readPayment($buildSubject);
        $paymentData = $payment->getPayment();
        $order = $payment->getOrder();
        $orderId = $order->getId();

        /** Get the config of the current Apm provider */
        $config = $this->configFactory->create($paymentData->getMethod());

        $txnType = $config->getPaymentAction() === MethodInterface::ACTION_AUTHORIZE ? 'authorize' : 'charge';

        $outputArray = [];

        $outputArray['TXN_TYPE'] = $txnType;
        $outputArray['AMOUNT'] = $this->orderRepository->get($orderId)->getGrandTotal();
        $outputArray['CONFIG'] = $config;
        $outputArray['CURRENCY'] = $order->getCurrencyCode();
        $outputArray['CUSTOMER_DATA'] = $this->getCustomerData($order);
        $outputArray['DESCRIPTOR'] = $this->getDescriptor($orderId);
        $outputArray['ORDER_ID'] = (string) $orderId;
        $outputArray['PROVIDER_DATA'] = $config->getProviderEndpoints();
        $outputArray['SERVICES_CONFIG'] = $config->getBackendGatewayOptions();
        $outputArray['DIUI_APM'] = $paymentData->getAdditionalInformation('diuiApmPayment');

        return $outputArray;
    }

    /**
     * Get customer data.
     *
     * @param OrderAdapterInterface $order
     * @return array
     */
    private function getCustomerData($order)
    {
        $billingAddress = $order->getBillingAddress();
        $accountHolderName = $this->utils->sanitizeString($billingAddress->getFirstname()) . ' ' .
            $this->utils->sanitizeString($billingAddress->getLastname());

        return [
            'accountHolderName' => $accountHolderName,
            'country' => $billingAddress->getCountryId(),
        ];
    }

    /**
     * Get the remittance reference information.
     *
     * @param int $orderId
     * @return string
     */
    private function getDescriptor($orderId)
    {
        return 'ORD' . $orderId;
    }
}
