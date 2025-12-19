<?php

namespace GlobalPayments\PaymentGateway\Gateway\Request\HostedPaymentPages;

use GlobalPayments\PaymentGateway\Gateway\ConfigFactory;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class InitiatePaymentRequest implements BuilderInterface
{
    /**
     * @var ConfigFactory
     */
    private $configFactory;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * Constructor
     * 
     * @param LoggerInterface $logger
     * @param ConfigFactory $configFactory
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        LoggerInterface $logger,
        ConfigFactory $configFactory,
        StoreManagerInterface $storeManager
    ) {
        $this->logger = $logger;
        $this->configFactory = $configFactory;
        $this->storeManager = $storeManager;
    }

    /**
     * @inheritDoc
     */
    public function build(array $buildSubject)
    {
        try {
            $paymentDO = SubjectReader::readPayment($buildSubject);
            $payment = $paymentDO->getPayment();
            $order = $paymentDO->getOrder();
            
            // HPP is now part of unified payment methods - use the actual payment method config
            $config = $this->configFactory->create($payment->getMethod());
            
            // Get amount directly from order like other async payment methods
            $amount = $order->getGrandTotalAmount();

            // Get the actual order entity ID from payment object for database lookup
            $orderEntityId = $payment->getOrder()->getId();

            $requestData = [
                'TXN_TYPE' => 'sale',
                'ORDER_ID' => $order->getOrderIncrementId(),
                'ORDER_ENTITY_ID' => $orderEntityId,
                'AMOUNT' => $amount,
                'CURRENCY' => $order->getCurrencyCode(),
                'CONFIG' => $config,
                'SERVICES_CONFIG' => $config->getBackendGatewayOptions(),
                'STORE_NAME' => $this->getStoreName($order),
            ];

            // Add customer information
            $billingAddress = $order->getBillingAddress();

            if ($billingAddress) {
                $requestData['CUSTOMER_FIRST_NAME'] = $billingAddress->getFirstname();
                $requestData['CUSTOMER_LAST_NAME'] = $billingAddress->getLastname();
                $requestData['CUSTOMER_EMAIL'] = $billingAddress->getEmail();
                $requestData['CUSTOMER_PHONE'] = $billingAddress->getTelephone();
                
                // Add phone country code based on billing country
                $requestData['PHONE_COUNTRY_CODE'] = $this->getPhoneCountryCode($billingAddress->getCountryId());
                
                // Billing address
                $requestData['BILLING_ADDRESS_1'] = $billingAddress->getStreetLine1();
                $requestData['BILLING_ADDRESS_2'] = $billingAddress->getStreetLine2();
                $requestData['BILLING_CITY'] = $billingAddress->getCity();
                $requestData['BILLING_STATE'] = $billingAddress->getRegionCode();
                $requestData['BILLING_POSTCODE'] = $billingAddress->getPostcode();
                $requestData['BILLING_COUNTRY'] = $billingAddress->getCountryId();
            } else {
                $this->logger->warning('HPP InitiatePaymentRequest - No billing address found');
            }

            // Add shipping information if available
            $shippingAddress = $order->getShippingAddress();

            if ($shippingAddress) {
                $requestData['SHIPPING_ADDRESS_1'] = $shippingAddress->getStreetLine1();
                $requestData['SHIPPING_ADDRESS_2'] = $shippingAddress->getStreetLine2();
                $requestData['SHIPPING_CITY'] = $shippingAddress->getCity();
                $requestData['SHIPPING_STATE'] = $shippingAddress->getRegionCode();
                $requestData['SHIPPING_POSTCODE'] = $shippingAddress->getPostcode();
                $requestData['SHIPPING_COUNTRY'] = $shippingAddress->getCountryId();
            }

            // Add return URL (this would typically come from store config)
            $requestData['RETURN_URL'] = $this->getStoreBaseUrl($order);

            // For HPP flow, we use the order increment ID as reference since order should exist before redirect
            // This aligns with other async payment methods where order is created before payment
            $requestData['REFERENCE'] = 'Order-' . $order->getOrderIncrementId();

            return $requestData;

        } catch (\Exception $e) {
            $this->logger->critical('HPP InitiatePaymentRequest::build() - Exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    /**
     * Get store base URL
     * 
     * @param \Magento\Payment\Gateway\Data\OrderAdapterInterface $order
     * @return string
     */
    private function getStoreBaseUrl(\Magento\Payment\Gateway\Data\OrderAdapterInterface $order): string
    {
        try {
            $storeId = $order->getStoreId();
            $store = $this->storeManager->getStore($storeId);

            return $store->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_WEB);
        } catch (\Exception $e) {
            $this->logger->error('HPP InitiatePaymentRequest - Error getting store base URL', [
                'error' => $e->getMessage(),
                'store_id' => $order->getStoreId() ?? 'unknown'
            ]);
            
            // Fallback to default store URL
            try {
                return $this->storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_WEB);
            } catch (\Exception $fallbackException) {
                $this->logger->critical('HPP InitiatePaymentRequest - Failed to get any store URL', [
                    'fallback_error' => $fallbackException->getMessage()
                ]);

                throw new \Exception('Unable to determine store URL for HPP return processing');
            }
        }
    }


    /**
     * Get phone country code based on country ID
     *
     * @param string|null $countryId
     * @return string
     */
    private function getPhoneCountryCode(?string $countryId): string
    {
        // Map common countries to their phone country codes
        $countryPhoneCodes = [
            'US' => '1',  // United States
            'CA' => '1',  // Canada
            'GB' => '44', // United Kingdom
            'IE' => '353', // Ireland
            'AU' => '61', // Australia
            'NZ' => '64', // New Zealand
            'DE' => '49', // Germany
            'FR' => '33', // France
            'ES' => '34', // Spain
            'IT' => '39', // Italy
            'NL' => '31', // Netherlands
            'BE' => '32', // Belgium
            'AT' => '43', // Austria
            'CH' => '41', // Switzerland
            'SE' => '46', // Sweden
            'NO' => '47', // Norway
            'DK' => '45', // Denmark
            'FI' => '358', // Finland
        ];

        return $countryPhoneCodes[$countryId] ?? '1'; // Default to US/Canada code
    }

    /**
     * Get store name
     *
     * @param \Magento\Payment\Gateway\Data\OrderAdapterInterface $order
     * @return string
     */
    private function getStoreName(\Magento\Payment\Gateway\Data\OrderAdapterInterface $order): string
    {
        try {
            $storeId = $order->getStoreId();
            $store = $this->storeManager->getStore($storeId);

            return $store->getName();
        } catch (\Exception $e) {
            $this->logger->warning('HPP InitiatePaymentRequest - Error getting store name', [
                'error' => $e->getMessage()
            ]);

            return 'Magento Store';
        }
    }
}
