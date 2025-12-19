<?php

namespace GlobalPayments\PaymentGateway\Controller\Adminhtml\Configuration;

use Exception;
use GlobalPayments\Api\Entities\Enums\Environment;
use GlobalPayments\PaymentGateway\Gateway\Command\GetAccessTokenCommand;
use GlobalPayments\PaymentGateway\Gateway\Config;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Store\Model\ScopeInterface;

class CredentialsCheck extends Action
{
    public const ADMIN_RESOURCE = 'Magento_Config::config';

    /**
     * @var Config
     */
    protected Config $config;

    /**
     * @var GetAccessTokenCommand
     */
    protected GetAccessTokenCommand $getAccessTokenCommand;

    /**
     * CredentialsCheck constructor.
     *
     * @param Context $context
     * @param Config $config
     * @param GetAccessTokenCommand $getAccessTokenCommand
     */
    public function __construct(
        Context $context,
        Config $config,
        GetAccessTokenCommand $getAccessTokenCommand
    ) {
        parent::__construct($context);
        $this->config = $config;
        $this->getAccessTokenCommand = $getAccessTokenCommand;
    }

    /**
     * @inheritDoc
     */
    public function execute()
    {
        $response = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $request = $this->getRequest();
        $environment = (int) $request->getParam('isSandboxMode') === 1 ? Environment::TEST : Environment::PRODUCTION;
        $appId = $request->getParam('app_id');
        $appKey = $request->getParam('app_key');
        $storeId = $request->getParam('storeId', 0);

        /*
         * If the app key from the request has '*' characters (masked default app key), use the default app key.
         */
            if (false !== strpos($appKey, '*')) {
                $appKey = $this->config->getCredentialSetting('app_key', $storeId);
                if(!$appKey){
                
                    $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
                    $scopeConfig = $objectManager->get(\Magento\Framework\App\Config\ScopeConfigInterface::class);
                    
                    $isSandboxMode = (int) $request->getParam('isSandboxMode') === 1;
                    $configPath = 'payment/globalpayments_paymentgateway_gpApi/' . ($isSandboxMode ? 'sandbox_app_key' : 'app_key');
                    $appKey = $scopeConfig->getValue($configPath, ScopeInterface::SCOPE_STORE, $storeId);
                }
            }

        $configData = [
            'environment' => $environment,
            'appId' => $appId,
            'appKey' => $appKey,
            'isCredentialCheck' => true
        ];

        try {
            $result = $this->getAccessTokenCommand->execute([
                'gatewayConfig' => $this->config,
                'configData' => $configData
            ])->get();

            if (!isset($result['error'])) {
                $response->setData([
                    'error' => false,
                    'message' => __('Your credentials were successfully confirmed!'),
                    'accounts' => $result["accounts"]
                ]);
            } else {
                $response->setData([
                    'error' => true,
                    'message' => sprintf(
                        __('Unable to perform request. Invalid data. %1$s'),
                        $result['message']
                    )
                ]);
            }
        } catch (Exception $e) {
            $response->setData([
                'error' => true,
                'message' => sprintf(
                    __('Unable to perform request. Invalid data. %1$s'),
                    $e->getMessage()
                )
            ]);
        }

        return $response;
    }
}
