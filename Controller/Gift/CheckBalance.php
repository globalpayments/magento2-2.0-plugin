<?php

namespace GlobalPayments\PaymentGateway\Controller\Gift;

use Magento\Framework\App\Action\Context as Context;
use Magento\Framework\App\Action\Action;
use GlobalPayments\PaymentGateway\Model\Helper\GiftHelper as GiftHelper;

class CheckBalance extends Action
{
    /**
     * @var GiftHelper GiftHelper
     */
    private $giftHelper;

    /**
     * CheckBalance constructor.
     *
     * @param Context $context
     * @param GiftHelper $giftHelper
     */
    public function __construct(Context $context, GiftHelper $giftHelper)
    {
        parent::__construct($context);

        $this->giftHelper = $giftHelper;
    }

    /**
     * @inheritDoc
     */
    public function execute()
    {
        $giftCardCode = $this->getRequest()->getParam('giftcard_number');
        $pin = $this->getRequest()->getParam('giftcard_pin');

        if (empty($pin)) {
            $pin = null;
        } else {
            $pin = (int)$pin;
        }

        try {
            $result = $this->giftHelper->checkGiftCardBalance($giftCardCode, $pin);
        } catch (\Exception $e) {
            $result = ['error' => true, 'message' => $e->getMessage()];
        }

        $this->getResponse()->setHeader('Content-type', 'application/json');
        $this->getResponse()->setBody(json_encode($result));

        return null;
    }
}
