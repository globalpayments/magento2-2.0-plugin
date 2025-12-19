<?php
namespace GlobalPayments\PaymentGateway\Model\Helper;

use Magento\Framework\Model\AbstractModel;
use Magento\Checkout\Model\Session;
use Magento\Framework\Model\Context as Context;
use Magento\Framework\Registry;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Data\Collection\AbstractDb;

use GlobalPayments\PaymentGateway\Gateway\Config;
use GlobalPayments\Api\Entities\Exceptions\ApiException;
use GlobalPayments\Api\Entities\Enums\ExceptionCodes;

class FraudManagementHelper extends AbstractModel
{
    public const FRAUD_TEXT_DEFAULT              = '%s';
    public const FRAUD_VELOCITY_ATTEMPTS_DEFAULT = 3;
    public const FRAUD_VELOCITY_TIMEOUT_DEFAULT  = 10;
    public const DEFAULT_PATH_PATTERN = 'globalpayments/%s/%s';
    public const METHOD_CODE = 'fraudmanagement';

    protected $_fraud_velocity_attempts     = null;
    protected $_fraud_velocity_timeout      = null;
    protected $_enable_anti_fraud           = null;
    protected $_allow_fraud                 = null;
    protected $_email_fraud                 = null;
    protected $_fraud_address               = null;
    protected $_fraud_text                  = null;
    protected $_use_iframes                 = null;

    /**
     * @var GlobalPayments\PaymentGateway\Gateway\Config
     */
    protected $config;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    private $_checkoutSession;

    /**
     * FraudManagementHelper constructor.
     *
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
        $this->_checkoutSession = $session;
    }

    public function getFraudSettings()
    {
        $this->config->setPathPattern(self::DEFAULT_PATH_PATTERN);
        $this->config->setMethodCode(self::METHOD_CODE);
        if ($this->_enable_anti_fraud === null) {
            $this->_enable_anti_fraud       = (bool) $this->config->getValue('enable_anti_fraud');
            $this->_allow_fraud             = $this->config->getValue('allow_fraud');
            $this->_email_fraud             = $this->config->getValue('email_fraud');
            $this->_fraud_address           = $this->config->getValue('fraud_address');
            $this->_fraud_text              = $this->config->getValue('fraud_text');
            $this->_fraud_velocity_attempts = (int) $this->config->getValue('fraud_velocity_attempts');
            $this->_fraud_velocity_timeout  = (int) $this->config->getValue('fraud_velocity_timeout');

            if ($this->_fraud_text === null) {
                $this->_fraud_text = self::FRAUD_TEXT_DEFAULT;
            }

            if ($this->_fraud_velocity_attempts === null
                || !is_numeric($this->_fraud_velocity_attempts)
            ) {
                $this->_fraud_velocity_attempts = self::FRAUD_VELOCITY_ATTEMPTS_DEFAULT;
            }

            if ($this->_fraud_velocity_timeout === null
                || !is_numeric($this->_fraud_velocity_timeout)
            ) {
                $this->_fraud_velocity_timeout = self::FRAUD_VELOCITY_TIMEOUT_DEFAULT;
            }
        }
    }

    protected function maybeResetVelocityTimeout()
    {
        $timeoutExpiration = (int)$this->getVelocityVar('TimeoutExpiration');

        if (time() < $timeoutExpiration) {
            return;
        }

        $this->unsVelocityVar('Count');
        $this->unsVelocityVar('IssuerResponse');
        $this->unsVelocityVar('TimeoutExpiration');
    }

    public function checkVelocity()
    {
        if ($this->_enable_anti_fraud !== true) {
            return;
        }

        $this->maybeResetVelocityTimeout();

        $count = (int)$this->getVelocityVar('Count');
        $issuerResponse = (string)$this->getVelocityVar('IssuerResponse');
        $timeoutExpiration = (int)$this->getVelocityVar('TimeoutExpiration');

        if ($count >= $this->_fraud_velocity_attempts
            && time() < $timeoutExpiration
        ) {
            sleep(5);
            throw new ApiException(sprintf($this->_fraud_text, $issuerResponse));
        }
    }

    public function updateVelocity($e)
    {
        if ($this->_enable_anti_fraud != true) {
            return;
        }

        $this->maybeResetVelocityTimeout();

        $count = (int)$this->getVelocityVar('Count');
        $issuerResponse = (string)$this->getVelocityVar('IssuerResponse');
        if ($issuerResponse !== $e->getMessage()) {
            $issuerResponse = $e->getMessage();
        }

        //NOW    + (fraud velocity timeout in seconds)
        $timeoutExpiration = time() + ($this->_fraud_velocity_timeout * 60);

        $this->setVelocityVar('Count', $count + 1);
        $this->setVelocityVar('IssuerResponse', $issuerResponse);
        $this->setVelocityVar('TimeoutExpiration', $timeoutExpiration);

        if ($this->_allow_fraud && $this->_email_fraud && $this->_fraud_address != '') {
            // EMAIL THE PEOPLE
            $order = $this->_checkoutSession->getLastRealOrder();
            $this->sendEmail(
                $this->_fraud_address,
                $this->_fraud_address,
                'Suspicious order (' . $order->getIncrementId() . ') allowed',
                'Hello,<br><br>Heartland has determined that you should review order ' .
                    $order->getRealOrderId() . ' for the amount of ' . $order->getGrandTotalAmount() . '.'
            );
        }
    }

    protected function getVelocityVar($var)
    {
        return $this->_checkoutSession
            ->getData($this->getVelocityVarPrefix() . $var);
    }

    protected function setVelocityVar($var, $data = null)
    {
        return $this->_checkoutSession
            ->setData($this->getVelocityVarPrefix() . $var, $data);
    }

    protected function unsVelocityVar($var)
    {
        return $this->_checkoutSession
        ->unsetData($this->getVelocityVarPrefix() . $var);
    }

    protected function getVelocityVarPrefix()
    {
        return sprintf('GlopalPayments_Velocity%s', hash('sha256', $this->getRemoteIP()));
    }

    protected function getRemoteIP()
    {
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            $remoteIP = $_SERVER['HTTP_CF_CONNECTING_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $remoteIPArray = array_values(
                array_filter(
                    explode(
                        ',',
                        $_SERVER['HTTP_X_FORWARDED_FOR']
                    )
                )
            );
            $remoteIP = end($remoteIPArray);
        } else {
            $remoteIP = $_SERVER['REMOTE_ADDR'];
        }
        return $remoteIP;
    }

    protected function sendEmail($to, $from, $subject, $body, $headers = [], $isHtml = true)
    {
        $headers[] = sprintf('From: %s', $from);
        $headers[] = sprintf('Reply-To: %s', $from);
        $message = $body;

        if ($isHtml) {
            $message = sprintf('<html><body>%s</body></html>', $body);
            $headers[] = 'MIME-Version: 1.0';
            $headers[] = 'Content-Type: text/html; charset=ISO-8859-1';
        }

        $message = wordwrap($message, 70, "\r\n");
        mail($to, $subject, $message, implode("\r\n", $headers));
    }
}
