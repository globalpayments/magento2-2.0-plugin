<?php

namespace GlobalPayments\PaymentGateway\Helper;

use Magento\Sales\Api\Data\OrderInterface;
use Magento\Payment\Model\InfoInterface;
use GlobalPayments\PaymentGateway\Gateway\Response\TxnIdHandler;
use GlobalPayments\PaymentGateway\Gateway\Config;
use Psr\Log\LoggerInterface;

/**
 * Visa Installments Helper
 * 
 * Centralized helper for retrieving and formatting Visa installment data
 */
class VisaInstallmentsHelper
{
    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @param LoggerInterface $logger
     */
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Check if order has Visa installments
     *
     * @param OrderInterface|null $order
     * @return bool
     */
    public  function hasVisaInstallments(?OrderInterface $order): bool
    {
        try {
            if (!$order || !$order->getId()) {
                return false;
            }

            $payment = $order->getPayment();
            if (!$payment) {
                return false;
            }

            // Check if payment method is GPAPI
            if ($payment->getMethod() !== Config::CODE_GPAPI) {
                return false;
            }

            return (bool) $payment->getAdditionalInformation('has_visa_installments');
        } catch (\Exception $e) {
            $this->logger->error('Visa Installments Helper: Error checking installments', [
                'error' => $e->getMessage(),
                'order_id' => $order ? $order->getId() : 'N/A'
            ]);
            return false;
        }
    }

    /**
     * Check if payment info has Visa installments (for direct payment access)
     *
     * @param InfoInterface|null $payment
     * @return bool
     */
    public  function paymentHasVisaInstallments(?InfoInterface $payment): bool
    {
        try {
            if (!$payment) {
                return false;
            }

            // Check if payment method is GPAPI
            if ($payment->getMethod() !== Config::CODE_GPAPI) {
                return false;
            }

            return (bool) $payment->getAdditionalInformation('has_visa_installments');
        } catch (\Exception $e) {
            $this->logger->error('Visa Installments Helper: Error checking payment installments', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get installment data from order
     *
     * @param OrderInterface|null $order
     * @return array|null
     */
    public function getInstallmentData(?OrderInterface $order): ?array
    {
        try {
            if (!$order || !$order->getId()) {
                return null;
            }

            $payment = $order->getPayment();
            if (!$payment) {
                return null;
            }

            return $this->getInstallmentDataFromPayment($payment);
        } catch (\Exception $e) {
            $this->logger->error('Visa Installments Helper: Error retrieving installment data', [
                'error' => $e->getMessage(),
                'order_id' => $order ? $order->getId() : 'N/A'
            ]);
            return null;
        }
    }

    /**
     * Get installment data from payment info
     *
     * @param InfoInterface|null $payment
     * @return array|null
     */
    public  function getInstallmentDataFromPayment(?InfoInterface $payment): ?array
    {
        try {
            if (!$payment) {
                return null;
            }

            $installmentDataJson = $payment->getAdditionalInformation(TxnIdHandler::VISA_INSTALLMENT_DATA);
            
            if (empty($installmentDataJson)) {
                return null;
            }

            $installmentData = json_decode($installmentDataJson, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->logger->error('Visa Installments Helper: Failed to decode installment JSON', [
                    'json_error' => json_last_error_msg()
                ]);
                return null;
            }

            return $installmentData;
        } catch (\Exception $e) {
            $this->logger->error('Visa Installments Helper: Error retrieving payment installment data', [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Get terms data from installment data
     *
     * @param array|null $installmentData
     * @return array|null
     */
    public static function getTermsData(?array $installmentData): ?array
    {
        if (!$installmentData) {
            return null;
        }

        // Extract terms from nested structure or use flat structure
        $termsData = $installmentData['terms'] ?? $installmentData;

        // Validate required fields exist
        if (empty($termsData['count'])) {
            return null;
        }

        return $termsData;
    }

    /**
     * Format amount from minor units to major units with currency symbol
     *
     * @param int $amount Amount in minor units (e.g., cents)
     * @param string $currencyCode Currency code
     * @return string Formatted amount with symbol
     */
    public static function formatAmount(int $amount, string $currencyCode = 'GBP'): string
    {
        $majorAmount = $amount / 100;
        
        switch (strtoupper($currencyCode)) {
            case 'GBP':
                return '£' . number_format($majorAmount, 2);
            case 'USD':
                return '$' . number_format($majorAmount, 2);
            case 'EUR':
                return '€' . number_format($majorAmount, 2);
            case 'CAD':
                return 'CA$' . number_format($majorAmount, 2);
            default:
                return $currencyCode . ' ' . number_format($majorAmount, 2);
        }
    }

    /**
     * Get installment count
     *
     * @param array|null $termsData
     * @return string
     */
    public static function getInstallmentCount(?array $termsData): string
    {
        if (!$termsData || empty($termsData['count'])) {
            return '';
        }

        return (string) $termsData['count'];
    }

    /**
     * Get time unit (e.g., "month", "months")
     *
     * @param array|null $termsData
     * @return string
     */
    public static function getTimeUnit(?array $termsData): string
    {
        if (!$termsData) {
            return 'month';
        }

        $unit = strtolower($termsData['time_unit'] ?? 'month');
        $count = (int) ($termsData['count'] ?? 1);

        // Pluralize if needed
        if ($count > 1 && substr($unit, -1) !== 's') {
            $unit .= 's';
        }

        return $unit;
    }

    /**
     * Get interest rate
     *
     * @param array|null $termsData
     * @return string|null
     */
    public static function getInterestRate(?array $termsData): ?string
    {
        if (!$termsData || !isset($termsData['cost_percentage'])) {
            return null;
        }

        return $termsData['cost_percentage'] . '%';
    }

    /**
     * Get total plan cost formatted
     *
     * @param array|null $termsData
     * @param string $currency
     * @return string|null
     */
    public static function getTotalPlanCost(?array $termsData, string $currency = 'GBP'): ?string
    {
        if (!$termsData || !isset($termsData['total_plan_cost'])) {
            return null;
        }

        return self::formatAmount($termsData['total_plan_cost'], $currency);
    }

    /**
     * Get installment fees formatted
     *
     * @param array|null $termsData
     * @param string $currency
     * @return string|null
     */
    public static function getInstallmentFees(?array $termsData, ?string $currency = 'GBP'): ?string
    {
        if (!$termsData || !isset($termsData['fees']['total_amount'])) {
            return null;
        }

        return self::formatAmount($termsData['fees']['total_amount'], $currency);
    }

    /**
     * Get total amount formatted
     *
     * @param array|null $termsData
     * @param string $currencyCode
     * @return string|null
     */
    public static function getTotalAmount(?array $termsData, string $currencyCode = 'GBP'): ?string
    {
        if (!$termsData || !isset($termsData['total_amount'])) {
            return null;
        }

        return self::formatAmount($termsData['total_amount'], $currencyCode);
    }

    /**
     * Get payment frequency
     *
     * @param array|null $termsData
     * @return string|null
     */
    public static function getPaymentFrequency(?array $termsData): ?string
    {
        if (!$termsData || empty($termsData['time_unit'])) {
            return null;
        }

        return ucfirst(strtolower($termsData['time_unit']));
    }

    /**
     * Get currency code
     *
     * @param array|null $termsData
     * @return string
     */
    public static function getCurrency(?array $termsData): string
    {
        return $termsData['currency'] ?? 'GBP';
    }

    /**
     * Get description
     *
     * @param array|null $termsData
     * @return string|null
     */
    public static function getDescription(?array $termsData): ?string
    {
        if (!$termsData || empty($termsData['description'])) {
            return null;
        }

        return $termsData['description'];
    }

    /**
     * Get terms and conditions URL
     *
     * @param array|null $termsData
     * @return string|null
     */
    public static function getTermsUrl(?array $termsData): ?string
    {
        if (!$termsData || empty($termsData['terms_and_conditions_url'])) {
            return null;
        }

        return $termsData['terms_and_conditions_url'];
    }

    // TODO: comments and php84
    public static function formatVisaInstallmentsData(?array $visaInstallmentsData) : ?array{
    
    if(empty($visaInstallmentsData)){
            
            return [];
        }
        // Possably wrap this in try catch
        $visaInstallmentsData = (isset($visaInstallmentsData['terms'])) ? self::getTermsData($visaInstallmentsData) : $visaInstallmentsData;
        $currencyCode = $visaInstallmentsData['currency'];
        return  [
            (string)__("Visa Installments Information") => "",
            (string)__("Order Amount")         => self::formatAmount((int)$visaInstallmentsData['total_amount']
                 - (int)$visaInstallmentsData['fees']['total_amount'], $currencyCode),
            (string)__("Payment Plan")         => self::getInstallmentCount($visaInstallmentsData) ." ". self::getTimeUnit($visaInstallmentsData),
            (string)__("Monthly Amount")       => self::getTotalPlanCost($visaInstallmentsData) . " / " . 
                ucfirst(strtolower($visaInstallmentsData["time_unit"])) . (string)__(" (Inci fees)"),
            (string)__("Installment Fees")     => self::getInstallmentFees($visaInstallmentsData, $currencyCode),
            (string)__("Intrest Rate")         => self::getInterestRate($visaInstallmentsData) . " APR",
            (string)__("Total Amount")         => self::getTotalAmount($visaInstallmentsData , $currencyCode), 
            (string)__("Terms and Conditions") => self::getDescription($visaInstallmentsData),
            (string)__("More Information")     => self::getTermsUrl($visaInstallmentsData)
        ];
    }
}
