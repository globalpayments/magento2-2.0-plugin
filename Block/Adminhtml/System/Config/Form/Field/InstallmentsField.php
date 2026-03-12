<?php

namespace GlobalPayments\PaymentGateway\Block\Adminhtml\System\Config\Form\Field;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class InstallmentsField extends Field
{
    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @param Context $context
     * @param ScopeConfigInterface $scopeConfig
     * @param array $data
     */
    public function __construct(
        Context $context,
        ScopeConfigInterface $scopeConfig,
        array $data = []
    ) {
        $this->scopeConfig = $scopeConfig;
        parent::__construct($context, $data);
    }

    /**
     * @inheritDoc
     */
    public function render(AbstractElement $element)
    {
        if (!$this->isInstallmentsAvailable()) {
            return '';
        }

        return parent::render($element);
    }

    /**
     * Check if installments should be available based on base currency and default country
     *
     * @return bool
     */
    protected function isInstallmentsAvailable(): bool
    {
        $baseCurrency = $this->scopeConfig->getValue(
            'currency/options/base',
            ScopeInterface::SCOPE_STORE
        );

        $defaultCountry = $this->scopeConfig->getValue(
            'general/country/default',
            ScopeInterface::SCOPE_STORE
        );

        return $baseCurrency === 'MXN' && $defaultCountry === 'MX';
    }
}