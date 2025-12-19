<?php

namespace GlobalPayments\PaymentGateway\Gateway\Request\BuyNowPayLater;

use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Catalog\Model\Product as CatalogProduct;
use Magento\Framework\App\Area;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Sales\Api\Data\OrderAddressInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Store\Model\App\Emulation;
use Magento\Store\Model\StoreManagerInterface;
use GlobalPayments\Api\Entities\Address;
use GlobalPayments\Api\Entities\Customer;
use GlobalPayments\Api\Entities\Enums\BNPLShippingMethod;
use GlobalPayments\Api\Entities\Enums\PhoneNumberType;
use GlobalPayments\Api\Entities\PhoneNumber;
use GlobalPayments\Api\Entities\Product;
use GlobalPayments\Api\Utils\CountryUtils;
use GlobalPayments\PaymentGateway\Helper\Utils;
use GlobalPayments\PaymentGateway\Gateway\ConfigFactory;
use GlobalPayments\PaymentGateway\Model\BuyNowPayLater\Validation;

class InitiatePaymentRequest implements BuilderInterface
{
    /**
     * @var array
     */
    private $countries= ['US', 'CA'];

    /**
     * @var Emulation
     */
    private $appEmulation;

    /**
     * @var ConfigFactory
     */
    private $configFactory;

    /**
     * @var ImageHelper
     */
    private $imageHelper;

    /**
     * @var OrderInterface
     */
    private $order;

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var Utils
     */
    private $utils;

    /**
     * @var Validation
     */
    private $validation;

    /**
     * AuthorizationRequest constructor.
     *
     * @param Emulation $appEmulation
     * @param ConfigFactory $configFactory
     * @param ImageHelper $imageHelper
     * @param OrderRepositoryInterface $orderRepository
     * @param StoreManagerInterface $storeManager
     * @param Utils $utils
     * @param Validation $validation
     */
    public function __construct(
        Emulation $appEmulation,
        ConfigFactory $configFactory,
        ImageHelper $imageHelper,
        OrderRepositoryInterface $orderRepository,
        StoreManagerInterface $storeManager,
        Utils $utils,
        Validation $validation
    ) {
        $this->appEmulation = $appEmulation;
        $this->configFactory = $configFactory;
        $this->imageHelper = $imageHelper;
        $this->orderRepository = $orderRepository;
        $this->storeManager = $storeManager;
        $this->utils = $utils;
        $this->validation = $validation;
    }

    /**
     * @inheritDoc
     */
    public function build(array $buildSubject)
    {
        $payment = SubjectReader::readPayment($buildSubject);
        $paymentData = $payment->getPayment();
        $this->order = $this->orderRepository->get($payment->getOrder()->getId());

        /** Get billing and shipping address and validate them */
        $orderBillingAddress = $this->order->getBillingAddress();
        $orderShippingAddress = $this->order->getShippingAddress();

        /** Get the config of the current BNPL provider */
        $config = $this->configFactory->create($paymentData->getMethod());
        $this->validation->validate($orderBillingAddress, $orderShippingAddress, $config->isShippingRequired());

        $billingAddress = $this->mapAddress($orderBillingAddress);
        $shippingMethod = $this->getShippingMethod();
        if ($shippingMethod === BNPLShippingMethod::EMAIL) {
            $shippingAddress = $billingAddress;
        } else {
            $shippingAddress = $this->mapAddress($orderShippingAddress);
        }

        return [
            'TXN_TYPE' => 'authorization',
            'AMOUNT' => $this->order->getGrandTotal(),
            'BILLING_ADDRESS' => $billingAddress,
            'CURRENCY' => $this->order->getOrderCurrencyCode(),
            'CUSTOMER_DATA' => $this->getCustomerData(),
            'ORDER_ID' => $this->order->getId(),
            'PRODUCTS_DATA' => $this->getProductData(),
            'PROVIDER_DATA' => $config->getProviderEndpoints(),
            'SERVICES_CONFIG' => $config->getBackendGatewayOptions(),
            'SHIPPING_ADDRESS' => $shippingAddress,
            'SHIPPING_METHOD' => $shippingMethod
        ];
    }

    /**
     * Get all order items.
     *
     * @return array
     * @throws NoSuchEntityException
     */
    private function getProductData()
    {
        $product = new Product();
        $product->productId = $this->order->getId();
        $product->productName = __('Your order')->getText();
        $product->description = $product->productName;
        $product->quantity = 1;
        $product->unitPrice = $this->utils->formatNumberToTwoDecimalPlaces($this->order->getGrandTotal());
        $product->netUnitPrice = $product->unitPrice;
        $product->taxAmount = $this->utils->formatNumberToTwoDecimalPlaces($this->order->getTaxAmount());
//        $product->discountAmount = 0;
//        $product->taxPercentage = 0;
        $product->url = $this->storeManager->getStore()->getBaseUrl();
        $product->imageUrl = $this->getProductImageUrl();

        return [$product];
    }

    /**
     * Get the image url of a product.
     *
     * @param CatalogProduct $product
     * @return string
     * @throws NoSuchEntityException
     */
    private function getProductImageUrl($product = null)
    {
        $image = $product ? $product->getImage() : null;
        $storeId = $this->storeManager->getStore()->getId();
        $this->appEmulation->startEnvironmentEmulation($storeId, Area::AREA_FRONTEND, true);

        if ($image) {
            $imageUrl = $this->imageHelper->init($product, 'product_base_image')
                ->setImageFile($image)->getUrl();
        } else {
            $imageUrl = $this->imageHelper->getDefaultPlaceholderUrl('image');
        }

        $this->appEmulation->stopEnvironmentEmulation();

        return $imageUrl;
    }

    /**
     * Map the address from Magento to the specific class from the SDK.
     *
     * @param OrderAddressInterface $orderAddress
     * @return Address
     */
    private function mapAddress($orderAddress)
    {
        $address = new Address();
        $address->streetAddress1 = $orderAddress->getStreet()[0] ?? null;
        $address->streetAddress2 = $orderAddress->getStreet()[1] ?? null;
        $address->city = $orderAddress->getCity();
        $address->postalCode = $orderAddress->getPostcode();
        $address->country = $orderAddress->getCountryId();
        $state = $orderAddress->getRegionCode();
        if (in_array($address->country, $this->countries) && isset($state)) {
            $address->state = $state;
        } else {
            $address->state = $address->country;
        }

        return $address;
    }

    /**
     * Get customer data.
     *
     * @return Customer
     */
    private function getCustomerData()
    {
        $orderBilling = $this->order->getBillingAddress();
        $customer = new Customer();
        $customer->id = $this->order->getCustomerId();
        $customer->firstName = $this->utils->sanitizeString($orderBilling->getFirstname());
        $customer->lastName = $this->utils->sanitizeString($orderBilling->getLastname());
        $customer->email = $orderBilling->getEmail();
        $phoneCode = CountryUtils::getPhoneCodesByCountry($orderBilling->getCountryId());
        $customer->phone = new PhoneNumber($phoneCode[0], $orderBilling->getTelephone(), PhoneNumberType::HOME);

        return $customer;
    }

    /**
     * Get the shipping method based on the cart products.
     *
     * @return string
     */
    private function getShippingMethod()
    {
        $orderItems = $this->order->getItems();
        $isVirtualProduct = false;
        $needsShipping = false;

        foreach ($orderItems as $item) {
            if ($item->getIsVirtual()) {
                $isVirtualProduct = true;
            } else {
                $needsShipping = true;
            }
        }

        if ($isVirtualProduct && $needsShipping) {
            return BNPLShippingMethod::COLLECTION;
        }
        if ($needsShipping) {
            return BNPLShippingMethod::DELIVERY;
        }
        return BNPLShippingMethod::EMAIL;
    }
}
