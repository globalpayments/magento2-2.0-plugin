<?php

namespace GlobalPayments\PaymentGateway\Gateway\Response;

use Magento\Framework\App\ObjectManager;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Payment\Model\InfoInterface;
use Magento\Sales\Api\Data\OrderPaymentExtensionInterface;
use Magento\Sales\Api\Data\OrderPaymentExtensionInterfaceFactory;
use Magento\Vault\Api\Data\PaymentTokenFactoryInterface;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use Magento\Vault\Api\PaymentTokenManagementInterface;
use Magento\Vault\Api\PaymentTokenRepositoryInterface;

class VaultDetailsHandler implements HandlerInterface
{
    /**
     * @var EncryptorInterface
     */
    private $encryptor;

    /**
     * @var OrderPaymentExtensionInterfaceFactory
     */
    private $paymentExtensionFactory;

    /**
     * @var PaymentTokenFactoryInterface
     */
    private $paymentTokenFactory;

    /**
     * @var PaymentTokenManagementInterface
     */
    private $paymentTokenManagement;

    /**
     * @var PaymentTokenRepositoryInterface
     */
    private $paymentTokenRepository;

    /**
     * @var Json
     */
    private $serializer;

    /**
     * VaultDetailsHandler constructor.
     *
     * @param EncryptorInterface $encryptor
     * @param OrderPaymentExtensionInterfaceFactory $paymentExtensionFactory
     * @param PaymentTokenFactoryInterface $paymentTokenFactory
     * @param PaymentTokenManagementInterface $paymentTokenManagement
     * @param PaymentTokenRepositoryInterface $paymentTokenRepository
     * @param Json|null $serializer
     */
    public function __construct(
        EncryptorInterface $encryptor,
        OrderPaymentExtensionInterfaceFactory $paymentExtensionFactory,
        PaymentTokenFactoryInterface $paymentTokenFactory,
        PaymentTokenManagementInterface $paymentTokenManagement,
        PaymentTokenRepositoryInterface $paymentTokenRepository,
        ?Json $serializer = null
    ) {
        $this->encryptor = $encryptor;
        $this->paymentExtensionFactory = $paymentExtensionFactory;
        $this->paymentTokenFactory = $paymentTokenFactory;
        $this->paymentTokenManagement = $paymentTokenManagement;
        $this->paymentTokenRepository = $paymentTokenRepository;
        $this->serializer = $serializer ?: ObjectManager::getInstance()
            ->get(Json::class);
    }

    /**
     * @inheritdoc
     */
    public function handle(array $handlingSubject, array $response)
    {
        // Normal flow
        if (!empty($handlingSubject)) {
            $paymentDO = SubjectReader::readPayment($handlingSubject);
            $payment = $paymentDO->getPayment();
            $paymentToken = $this->getVaultPaymentToken($response);

            // if there is a multi use token, add it to extension attributes
            if ($paymentToken !== null) {
                $extensionAttributes = $this->getExtensionAttributes($payment);
                $extensionAttributes->setVaultPaymentToken($paymentToken);
            }

            return;
        }

        // Verify flow
        $paymentToken = $this->getVaultPaymentToken($response);
        $paymentToken->setCustomerId($response['CUSTOMER_ID']);
        $paymentToken->setPaymentMethodCode($response['GATEWAY_METHOD_CODE']);
        $paymentToken->setPublicHash($this->generatePublicHash($paymentToken));
        $paymentToken->setIsVisible(true);
        $paymentToken->setIsActive(true);
        $this->saveToken($paymentToken);
    }

    /**
     * Save the token to the database.
     *
     * @param PaymentTokenInterface $paymentToken
     * @return void
     */
    private function saveToken(PaymentTokenInterface $paymentToken)
    {
        $tokenDuplicate = $this->paymentTokenManagement->getByPublicHash(
            $paymentToken->getPublicHash(),
            $paymentToken->getCustomerId()
        );

        if (!empty($tokenDuplicate)) {
            if ($paymentToken->getIsVisible() || $tokenDuplicate->getIsVisible()) {
                $paymentToken->setEntityId($tokenDuplicate->getEntityId());
                $paymentToken->setIsVisible(true);
            } elseif ($paymentToken->getIsVisible() === $tokenDuplicate->getIsVisible()) {
                $paymentToken->setEntityId($tokenDuplicate->getEntityId());
            } else {
                $paymentToken->setPublicHash(
                    $this->encryptor->getHash(
                        $paymentToken->getPublicHash() . $paymentToken->getGatewayToken()
                    )
                );
            }
        }

        $this->paymentTokenRepository->save($paymentToken);
    }

    /**
     * Get payment extension attributes
     *
     * @param InfoInterface $payment
     * @return OrderPaymentExtensionInterface
     */
    private function getExtensionAttributes(InfoInterface $payment)
    {
        $extensionAttributes = $payment->getExtensionAttributes();
        if (null === $extensionAttributes) {
            $extensionAttributes = $this->paymentExtensionFactory->create();
            $payment->setExtensionAttributes($extensionAttributes);
        }
        return $extensionAttributes;
    }

    /**
     * Get vault payment token entity.
     *
     * @param array $response
     * @return PaymentTokenInterface|null
     * @throws \Exception
     */
    private function getVaultPaymentToken(array $response)
    {
        if (empty($response['MULTI_USE_TOKEN'])) {
            return null;
        }

        $tokenDetails = $response['TOKEN_RESPONSE']['details'];

        $expDate = $this->getExpirationDate(
            $tokenDetails['expiryMonth'],
            $tokenDetails['expiryYear']
        );

        /** @var PaymentTokenInterface $paymentToken */
        $paymentToken = $this->paymentTokenFactory->create(
            PaymentTokenFactoryInterface::TOKEN_TYPE_CREDIT_CARD
        );

        $paymentToken->setGatewayToken($response['MULTI_USE_TOKEN']);
        $paymentToken->setExpiresAt($expDate);

        $paymentToken->setTokenDetails($this->convertDetailsToJSON([
            'type' => isset($tokenDetails['cardType']) ? strtolower($tokenDetails['cardType']) : '',
            'maskedCC' => $tokenDetails['cardLast4'] ?: substr($response['MULTI_USE_TOKEN'], -4),
            'expirationDate' => $tokenDetails['expiryMonth'] . '/' . $tokenDetails['expiryYear']
        ]));

        return $paymentToken;
    }

    /**
     * Generate vault payment public hash
     *
     * @param PaymentTokenInterface $paymentToken
     * @return string
     */
    private function generatePublicHash(PaymentTokenInterface $paymentToken)
    {
        $hashKey = $paymentToken->getGatewayToken();
        if ($paymentToken->getCustomerId()) {
            $hashKey = $paymentToken->getCustomerId();
        }

        $hashKey .= $paymentToken->getPaymentMethodCode()
            . $paymentToken->getType()
            . $paymentToken->getTokenDetails();

        return $this->encryptor->getHash($hashKey);
    }

    /**
     * Convert payment token details to JSON
     *
     * @param array $details
     * @return string
     */
    private function convertDetailsToJSON($details)
    {
        $json = $this->serializer->serialize($details);
        return $json ? $json : '{}';
    }

    /**
     * Get Expiration Date
     *
     * @param string $month
     * @param string $year
     * @return string
     * @throws \Exception
     */
    private function getExpirationDate($month, $year)
    {
        $expDate = new \DateTime(
            $year
            . '-'
            . $month
            . '-'
            . '01'
            . ' '
            . '00:00:00',
            new \DateTimeZone('UTC')
        );
        $expDate->add(new \DateInterval('P1M'));
        return $expDate->format('Y-m-d 00:00:00');
    }
}
