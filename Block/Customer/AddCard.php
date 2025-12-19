<?php

namespace GlobalPayments\PaymentGateway\Block\Customer;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Template;
use GlobalPayments\Api\Entities\Exceptions\ApiException;
use GlobalPayments\PaymentGateway\Model\Ui\ConfigProvider;

class AddCard extends Template
{
    /**
     * @var ConfigProvider
     */
    private $configProvider;

    /**
     * @var UrlInterface
     */
    private $urlBuilder;

    /**
     * AddCard constructor.
     *
     * @param ConfigProvider $configProvider
     * @param Template\Context $context
     * @param UrlInterface $urlBuilder
     * @param array $data
     */
    public function __construct(
        ConfigProvider $configProvider,
        Template\Context $context,
        UrlInterface $urlBuilder,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->configProvider = $configProvider;
        $this->urlBuilder = $urlBuilder;
    }

    /**
     * Get the link for the ProcessCardData controller.
     *
     * @return string
     */
    public function getFormAction()
    {
        return $this->urlBuilder->getUrl(
            'globalpayments/customer/processCardData',
            ['_secure' => true]
        );
    }

    /**
     * Returns the configuration for the template.
     *
     * @return array
     * @throws ApiException
     */
    public function getConfig()
    {
        return $this->configProvider->getConfig()['payment'][ConfigProvider::CODE];
    }
}
