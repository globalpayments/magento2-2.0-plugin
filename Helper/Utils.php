<?php

namespace GlobalPayments\PaymentGateway\Helper;

use Magento\Framework\Filter\Translit;
use GlobalPayments\Api\Entities\Enums\TransactionStatus;

class Utils
{
    /**
     * @var Translit
     */
    private $translit;

    /**
     * Utils constructor.
     *
     * @param Translit $translit
     */
    public function __construct(
        Translit $translit
    ) {
        $this->translit = $translit;
    }

    /**
     * Sanitize string.
     *
     * @param string $string
     * @return string
     */
    public function sanitizeString($string)
    {
        $string = $this->translit->filter($string);
        return preg_replace("/[^a-zA-Z-_.]/", "", $string);
    }

    /**
     * Converts API response code to user friendly message.
     *
     * @param string $responseCode
     *
     * @return string
     */
    public function mapResponseCodeToFriendlyMessage($responseCode = '')
    {
        switch ($responseCode) {
            case TransactionStatus::DECLINED:
            case 'FAILED':
                return __('Your payment was unsuccessful. Please try again or use a different payment method.');
            default:
                return __('An error occurred while processing the payment.
                    Please try again or use a different payment method.');
        }
    }

    /**
     * Strip the null values from an array.
     *
     * @param array $array configuration values
     * @return array
     */
    public function stripNullValues($array)
    {
        return array_filter($array, function ($value) {
            return $value !== null;
        });
    }

    /**
     * Format number to 2 decimal places.
     *
     * @param float $number
     * @return string
     */
    public function formatNumberToTwoDecimalPlaces($number)
    {
        return number_format($number, 2, '.', '');
    }
}
