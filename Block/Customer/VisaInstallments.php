<?php

namespace GlobalPayments\PaymentGateway\Block\Customer;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Sales\Api\OrderRepositoryInterface;
use GlobalPayments\PaymentGateway\Helper\VisaInstallmentsHelper;
use Psr\Log\LoggerInterface;

/**
 * Visa Installments Display Block
 * 
 * Displays Visa installment payment plan details on the success page
 */
class VisaInstallments extends Template
{
    /**
     * @var CheckoutSession
     */
    protected $checkoutSession;

    /**
     * @var OrderRepositoryInterface
     */
    protected $orderRepository;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var VisaInstallmentsHelper
     */
    protected $installmentsHelper;

    /**
     * @param Context $context
     * @param CheckoutSession $checkoutSession
     * @param OrderRepositoryInterface $orderRepository
     * @param LoggerInterface $logger
     * @param VisaInstallmentsHelper $installmentsHelper
     * @param array $data
     */
    public function __construct(
        Context $context,
        CheckoutSession $checkoutSession,
        OrderRepositoryInterface $orderRepository,
        LoggerInterface $logger,
        VisaInstallmentsHelper $installmentsHelper,
        array $data = []
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->orderRepository = $orderRepository;
        $this->logger = $logger;
        $this->installmentsHelper = $installmentsHelper;
        parent::__construct($context, $data);
    }

    /**
     * Checks if order has Visa installments
     *
     * @return bool
     */
    public function hasVisaInstallments(): bool
    {
        $order = $this->getOrder();
        return $this->installmentsHelper->hasVisaInstallments($order);
    }

    /**
     * Get visa installment data from order
     *
     * @return array|null
     */
    public function getInstallmentData(): ?array
    {
        $order = $this->getOrder();
        return $this->installmentsHelper->getInstallmentData($order);
    }

    /**
     * Get the order from checkout session
     * 
     * @throws Exception On failure to get order
     * @return \Magento\Sales\Model\Order\Interceptor|null
     */
    protected function getOrder(): ?\Magento\Sales\Model\Order\Interceptor
    {
        try {
            $orderId = $this->checkoutSession->getLastOrderId();
            
            if (!$orderId) {
                return null;
            }

            return $this->orderRepository->get($orderId);
        } catch (\Exception $e) {
            $this->logger->error('Visa Installments: Error loading order');
            return null;
        }
    }

    /**
     * Format amount
     *
     * @param int $amount Amount
     * @param string $currencyCode Currency code
     * @return string Formatted amount with currency symbol
     */
    public function formatAmount(int $amount, string $currencyCode = 'GBP'): string
    {
        return VisaInstallmentsHelper::formatAmount($amount, $currencyCode);
    }

    /**
     * Get currency code from order
     *
     * @throws Exception On failure to get order
     * @return string
     */
    public function getOrderCurrency(): string
    {
        try {
            $order = $this->getOrder();
            
            if ($order && $order->getId()) {
                return $order->getOrderCurrencyCode();
            }
        } catch (\Exception $e) {
            $this->logger->error('Visa Installments: Error getting order currency', [
                'error' => $e->getMessage()
            ]);
        }
        
        return 'GBP'; 
    }

    /**
     * Formats the installment data for email and success screen
     * 
     * @param array Installments Data array
     * @return array Formatted Installments data
     */
    public function getFormmatedInstallmentData(array $installmentData): array
    {
        return VisaInstallmentsHelper::formatVisaInstallmentsData($installmentData);
    }
}
