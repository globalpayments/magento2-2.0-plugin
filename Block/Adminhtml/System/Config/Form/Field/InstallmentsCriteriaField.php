<?php

namespace GlobalPayments\PaymentGateway\Block\Adminhtml\System\Config\Form\Field;

use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Store\Model\ScopeInterface;

class InstallmentsCriteriaField extends Field
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
        if (!$this->isFieldAvailable()) {
            return '';
        }

        return parent::render($element);
    }

    /**
     * Show field only for allowed country/currency combinations.
     */
    protected function isFieldAvailable(): bool
    {
        $baseCurrency = $this->scopeConfig->getValue(
            'currency/options/base',
            ScopeInterface::SCOPE_STORE
        );

        $defaultCountry = $this->scopeConfig->getValue(
            'general/country/default',
            ScopeInterface::SCOPE_STORE
        );

        return ($baseCurrency === 'GBP' && $defaultCountry === 'GB')
            || ($baseCurrency === 'CAD' && $defaultCountry === 'CA');
    }
}
