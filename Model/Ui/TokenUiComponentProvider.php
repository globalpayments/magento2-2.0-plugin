<?php

namespace GlobalPayments\PaymentGateway\Model\Ui;

use GlobalPayments\PaymentGateway\Gateway\Config;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use Magento\Vault\Model\Ui\TokenUiComponentInterface;
use Magento\Vault\Model\Ui\TokenUiComponentProviderInterface;
use Magento\Vault\Model\Ui\TokenUiComponentInterfaceFactory;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Template;

class TokenUiComponentProvider implements TokenUiComponentProviderInterface
{
    /**
     * @var TokenUiComponentInterfaceFactory
     */
    private $componentFactory;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var \Magento\Framework\UrlInterface
     */
    private $urlBuilder;

    /**
     * @var State
     */
    private $state;

    /**
     * TokenUiComponentProvider constructor.
     *
     * @param TokenUiComponentInterfaceFactory $componentFactory
     * @param UrlInterface $urlBuilder
     * @param State $state
     * @param Config $config
     */
    public function __construct(
        TokenUiComponentInterfaceFactory $componentFactory,
        UrlInterface $urlBuilder,
        State $state,
        Config $config
    ) {
        $this->componentFactory = $componentFactory;
        $this->urlBuilder = $urlBuilder;
        $this->state = $state;
        $this->config = $config;
    }

    /**
     * Get UI component for token.
     *
     * @param PaymentTokenInterface $paymentToken
     * @return TokenUiComponentInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getComponentForToken(PaymentTokenInterface $paymentToken)
    {
        $config = [];
        $name = '';

        if ($paymentToken->getPaymentMethodCode() == $this->config->getValue('code')) {
            $jsonDetails = json_decode($paymentToken->getTokenDetails() ?: '{}', true);
            $config = [
                'code' => $paymentToken->getPaymentMethodCode() . '_vault',
                TokenUiComponentProviderInterface::COMPONENT_DETAILS => $jsonDetails,
                TokenUiComponentProviderInterface::COMPONENT_PUBLIC_HASH => $paymentToken->getPublicHash()
            ];
            $name = $this->getComponentName();
        }
        if ($this->state->getAreaCode() == Area::AREA_ADMINHTML) {
            $config['template'] = 'GlobalPayments_PaymentGateway::form/vault.phtml';
        }
        $component = $this->componentFactory->create(
            [
                'config' => $config,
                'name' => $name,
            ]
        );

        return $component;
    }

    /**
     * Get UI component name for token.
     *
     * @return string
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function getComponentName()
    {
        if ($this->state->getAreaCode() == Area::AREA_ADMINHTML) {
            return Template::class;
        }

        return 'GlobalPayments_PaymentGateway/js/view/payment/method-renderer/vault';
    }
}
