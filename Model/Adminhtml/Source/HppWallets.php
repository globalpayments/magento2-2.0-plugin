<?php

namespace GlobalPayments\PaymentGateway\Model\Adminhtml\Source;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Data\OptionSourceInterface;
use Magento\Store\Model\ScopeInterface;

class HppWallets implements OptionSourceInterface
{
    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    public function __construct(ScopeConfigInterface $scopeConfig)
    {
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        $options = [
            [
                'value' => 'apple_pay',
                'label' => 'Apple Pay'
            ],
            [
                'value' => 'google_pay',
                'label' => 'Google Pay'
            ],
            [
                'value' => 'click_to_pay',
                'label' => 'Click to Pay'
            ],
            [
                'value' => 'blik',
                'label' => 'Blik'
            ],
            [
                'value' => 'bank_payment',
                'label' => 'Open Banking'
            ],
            [
                'value' => 'eraty',
                'label' => 'Eraty'
            ],
            [
                'value' => 'payu',
                'label' => 'PayU'
            ],
        ];

        $defaultCountry = strtoupper((string) $this->scopeConfig->getValue(
            'general/country/default',
            ScopeInterface::SCOPE_STORE
        ));
        $baseCurrency = strtoupper((string) $this->scopeConfig->getValue(
            'currency/options/base',
            ScopeInterface::SCOPE_STORE
        ));

        if ($defaultCountry === 'DE' && $baseCurrency === 'EUR') {
            $options[] = [
                'value' => 'cashpresso',
                'label' => 'Cashpresso'
            ];
        }

        return $options;
    }
}
