<?php
namespace GlobalPayments\PaymentGateway\Model\Helper;

use Magento\Framework\Model\AbstractModel;
use Magento\Checkout\Model\Session;
use Magento\Framework\Model\Context as Context;
use Magento\Framework\Registry;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Data\Collection\AbstractDb;
use GlobalPayments\PaymentGateway\Gateway\Config;
use GlobalPayments\Api\ServiceConfigs\Gateways\PorticoConfig;
use GlobalPayments\Api\ServiceConfigs\Gateways\GeniusConfig;
use GlobalPayments\Api\ServicesContainer;
use GlobalPayments\Api\PaymentMethods\GiftCard;
use GlobalPayments\Api\Entities\Enums\Environment;

class GiftHelper extends AbstractModel
{
    /**
     * @var GlobalPayments\PaymentGateway\Gateway\Config
     */
    protected $config;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    private $session;

    /**
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param GlobalPayments\PaymentGateway\Gateway\Config $config
     * @param \Magento\Checkout\Model\Session $session
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource|null $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        Config $config,
        Session $session,
        ?AbstractResource $resource = null,
        ?AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct($context, $registry, $resource, $resourceCollection, $data);

        $this->config = $config;
        $this->session = $session;
    }

    /**
     * Check gift card balance
     *
     * @param string $code
     * @param int|null $pin
     * @return array
     */
    public function checkGiftCardBalance($code, $pin = null)
    {
        if (empty($code) || ($this->config->getValue('enable_gift_card_bin') == 1 && empty($pin))) {
            return [
                'error' => true,
                'message' => __('Invalid gift card. Please check the entered card details and try again.')
            ];
        }

        $this->setupConfiguration();

        $card = new GiftCard();
        $card->number = $code;
        $card->pin = $pin;

        $response = $card->balanceInquiry()->execute();

        if ($response->responseCode === '00') {
            $currentQuote = $this->session->getQuote();
            $total = $currentQuote->getGrandTotal();

            $result = [
                'error' => false,
                'balance' => $response->balanceAmount,
                'less_than_total' => $response->balanceAmount < $total,
            ];

            return $result;
        }
    }

    public function giftCardSale($code, $amount, $currency, $pin = null)
    {
        $this->setupConfiguration();

        $card = new GiftCard();
        $card->number = $code;
        $card->pin = $pin;

        $gatewayResponse = $card->charge($amount)
            ->withCurrency($currency)
            ->execute();

        if ($gatewayResponse->responseCode === '00') {
            $response['RESULT_CODE'] = $gatewayResponse->responseCode;
            $response['TXN_ID'] = $gatewayResponse->transactionId;
            $response['AVS_CODE'] = (!empty($gatewayResponse->avsResponseCode)) ?
                $gatewayResponse->avsResponseCode : '';
            $response['CVN_CODE'] = (!empty($gatewayResponse->cvnResponseCode)) ?
                $gatewayResponse->cvnResponseCode : '';

            return $response;
        }
    }

    private function setupConfiguration()
    {
        $servicesConfig = [
            'gatewayMethodCode' => $this->config->getValue('code'),
            'secretApiKey' => $this->config->getCredentialSetting('secret_key'),
            'merchantName' => $this->config->getCredentialSetting('name'),
            'merchantSiteId' => $this->config->getCredentialSetting('site_id'),
            'merchantKey' => $this->config->getCredentialSetting('key'),
            'environment' => ($this->config->getValue('sandbox_mode') == 1) ?
                    Environment::TEST : Environment::PRODUCTION
        ];

        switch ($servicesConfig['gatewayMethodCode']) {
            case Config::CODE_HEARTLAND:
                $config = new PorticoConfig();
                break;
            case Config::CODE_GENIUS:
                $config = new GeniusConfig();
                break;
            default:
                break;
        }

        foreach ($servicesConfig as $key => $value) {
            if (property_exists($config, $key)) {
                $config->{$key} = $value;
            }
        }

        $config->developerId = "000000";
        $config->versionNumber = "0000";
        ServicesContainer::configureService($config);
    }
}
