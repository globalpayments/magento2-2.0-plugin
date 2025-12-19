<?php

namespace GlobalPayments\PaymentGateway\Helper;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Event\ManagerInterface as EventManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\QuoteRepository;
use Magento\Sales\Api\Data\OrderInterface;

class Checkout
{
    /**
     * @var CheckoutSession
     */
    private $checkoutSession;

    /**
     * @var EventManagerInterface
     */
    private $eventManager;

    /**
     * @var QuoteRepository
     */
    private $quoteRepository;

    /**
     * Checkout Helper constructor.
     *
     * @param CheckoutSession $checkoutSession
     * @param EventManagerInterface $eventManager
     * @param QuoteRepository $quoteRepository
     */
    public function __construct(
        CheckoutSession $checkoutSession,
        EventManagerInterface $eventManager,
        QuoteRepository $quoteRepository
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->eventManager = $eventManager;
        $this->quoteRepository = $quoteRepository;
    }

    /**
     * Fire the checkout specific events.
     *
     * @return void
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function fireEvents()
    {
        $order = $this->checkoutSession->getLastRealOrder();
        $quote = $this->checkoutSession->getQuote();

        $this->eventManager->dispatch(
            'checkout_onepage_controller_success_action',
            [
                'order_ids' => [$this->checkoutSession->getLastOrderId()],
                'order' => $order
            ]
        );

        $this->eventManager->dispatch(
            'sales_model_service_quote_submit_before',
            [
                'order' => $order,
                'quote' => $quote
            ]
        );
    }

    /**
     * Clear customer's cart.
     *
     * @param OrderInterface|null $order
     * @return void
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function clearQuote($order)
    {
        $quote = $this->checkoutSession->getQuote();
        $quote->setIsActive(false);
        $this->quoteRepository->save($quote);

        if ($order) {
            $this->checkoutSession->setLastOrderId($order->getId())
                ->setLastRealOrderId($order->getIncrementId());
        }
    }

    /**
     * Clear the current cart and fire the specific checkout events.
     *
     * @param OrderInterface|null $order
     * @return void
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function clearQuoteAndFireEvents($order)
    {
        $this->clearQuote($order);
        $this->fireEvents();
    }

    /**
     * Get the URL for the success page.
     *
     * @return string
     */
    public function getSuccessPageUrl()
    {
        return 'checkout/onepage/success';
    }

    /**
     * Get the URL for the cart page.
     *
     * @return string
     */
    public function getCartPageUrl()
    {
        return 'checkout/cart';
    }
}
