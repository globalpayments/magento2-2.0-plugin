<?php

namespace GlobalPayments\PaymentGateway\Model\HostedPaymentPages;

use GlobalPayments\PaymentGateway\Model\AbstractPaymentMethod;

/**
 * Hosted Payment Pages Configuration Model
 */
class Config extends AbstractPaymentMethod
{
    /**
     * Payment method code constant
     */
    public const CODE = 'globalpayments_paymentgateway_hpp';

    /**
     * @var string
     */
    protected $paymentMethodCode = self::CODE;

    /**
     * Check if HPP payment method is active
     * 
     * Follows the same pattern as other payment methods in the module
     * for consistent activation checking.
     *
     * @param string|null $storeId Store ID for scope-specific configuration
     * @return bool True if HPP is enabled, false otherwise
     */
    public function isActive(?string $storeId = null): bool
    {
        return (bool) $this->getValue('active', $storeId);
    }

    /**
     * Get credential setting with sandbox/production environment handling
     * 
     * Uses the same pattern as Gateway\Config::getCredentialSetting() 
     * but for HPP-specific sandbox/production credentials. Provides proper
     * handling of encrypted fields and environment-specific configurations.
     * 
     *
     * @param string $setting Configuration setting name
     * @param string|null $storeId Store ID for scope-specific configuration
     * @return string|null Configuration value based on current environment
     */
    public function getCredentialSetting(string $setting, ?string $storeId = null): ?string
    {
        return $this->getValue('sandbox_mode', $storeId) 
            ? $this->getValue('sandbox_' . $setting, $storeId) 
            : $this->getValue($setting, $storeId);
    }

    /**
     * Get backend gateway options for HPP configuration
     * 
     * Inherits base gateway configuration from parent and overrides with
     * HPP-specific settings. Follows the same pattern as other payment methods.
     *
     * @param string|null $storeId Store ID for scope-specific configuration
     * @return array Gateway configuration options
     */
    public function getBackendGatewayOptions(?string $storeId = null): array
    {
        // Get base gateway configuration (GP-API credentials, etc.)
        $baseOptions = parent::getBackendGatewayOptions($storeId);
        
        // Override gatewayMethodCode with HPP constant since parent method gets empty 'code' value
        $baseOptions['gatewayMethodCode'] = self::CODE;
        
        // Override with HPP-specific settings using getCredentialSetting for encrypted fields
        $baseOptions['appId'] = $this->getCredentialSetting('app_id', $storeId);
        $baseOptions['appKey'] = $this->getCredentialSetting('app_key', $storeId);
        $baseOptions['sandboxMode'] = $this->getValue('sandbox_mode', $storeId);
        
        return $baseOptions;
    }

    /**
     * Get frontend gateway options for HPP
     * 
     * HPP does not require frontend gateway options as it redirects
     * to an external hosted payment page.
     *
     * @param string|null $storeId Store ID for scope-specific configuration
     * @return array Empty array as no frontend options are needed
     */
    public function getFrontendGatewayOptions(?string $storeId = null): array
    {
        return [];
    }

    /**
     * Get enabled digital wallets as array
     * 
     * Returns an array of enabled digital wallet payment methods based on
     * the configuration settings.
     * 
     * Enhanced for PHP 8.4 compatibility with strict type hints
     *
     * @param string|null $storeId Store ID for scope-specific configuration
     * @return array Array of enabled digital wallet codes
     */
    public function getEnabledDigitalWallets(?string $storeId = null): array
    {
        $wallets = [];
        
        if ($this->getValue('enable_apple_pay', $storeId)) {
            $wallets[] = 'applepay';
        }
        
        if ($this->getValue('enable_google_pay', $storeId)) {
            $wallets[] = 'googlepay';
        }
        
        return $wallets;
    }

    /**
     * Get enabled alternative payment methods as array
     * 
     * Returns an array of enabled alternative payment methods (APMs) based on
     * the configuration settings.
     * 
     * Enhanced for PHP 8.4 compatibility with strict type hints
     *
     * @param string|null $storeId Store ID for scope-specific configuration
     * @return array Array of enabled APM codes
     */
    public function getEnabledAlternativePaymentMethods(?string $storeId = null): array
    {
        $apms = [];
        
        if ($this->getValue('enable_blik', $storeId)) {
            $apms[] = 'BLIK';
        }
        
        if ($this->getValue('enable_bank_payment', $storeId)) {
            $apms[] = 'BANK_PAYMENT';
        }
        
        if ($this->getValue('enable_payu', $storeId)) {
            $apms[] = 'PAYU';
        }
        
        return $apms;
    }

    /**
     * Check if Apple Pay is enabled
     *
     * @param string|null $storeId Store ID for scope-specific configuration
     * @return bool True if Apple Pay is enabled, false otherwise
     */
    public function isApplePayEnabled(?string $storeId = null): bool
    {
        return (bool) $this->getValue('enable_apple_pay', $storeId);
    }

    /**
     * Check if Google Pay is enabled
     *
     * @param string|null $storeId Store ID for scope-specific configuration
     * @return bool True if Google Pay is enabled, false otherwise
     */
    public function isGooglePayEnabled(?string $storeId = null): bool
    {
        return (bool) $this->getValue('enable_google_pay', $storeId);
    }

    /**
     * Check if BLIK is enabled
     *
     * @param string|null $storeId Store ID for scope-specific configuration
     * @return bool True if BLIK is enabled, false otherwise
     */
    public function isBlikEnabled(?string $storeId = null): bool
    {
        return (bool) $this->getValue('enable_blik', $storeId);
    }

    /**
     * Check if Bank Payment is enabled
     *
     * @param string|null $storeId Store ID for scope-specific configuration
     * @return bool True if Bank Payment is enabled, false otherwise
     */
    public function isBankPaymentEnabled(?string $storeId = null): bool
    {
        return (bool) $this->getValue('enable_bank_payment', $storeId);
    }

    /**
     * Check if PayU is enabled
     *
     * @param string|null $storeId Store ID for scope-specific configuration
     * @return bool True if PayU is enabled, false otherwise
     */
    public function isPayUEnabled(?string $storeId = null): bool
    {
        return (bool) $this->getValue('enable_payu', $storeId);
    }

    /**
     * Get redirect text to display during checkout
     *
     * @param string|null $storeId Store ID for scope-specific configuration
     * @return string Redirect text or default message
     */
    public function getRedirectText(?string $storeId = null): string
    {
        return $this->getValue('redirect_text', $storeId) ?: 'You will be redirected to the secure payment page to complete your order.';
    }

    /**
     * Get configured order status for successful payments
     *
     * @param string|null $storeId Store ID for scope-specific configuration
     * @return string Order status or default 'processing'
     */
    public function getOrderStatus(?string $storeId = null): string
    {
        return $this->getValue('order_status', $storeId) ?: 'processing';
    }

    /**
     * Get payment action configuration
     *
     * @param string|null $storeId Store ID for scope-specific configuration
     * @return string Payment action or default 'authorize_capture'
     */
    public function getPaymentAction(?string $storeId = null): string
    {
        // return $this->getValue('payment_action', $storeId) ?: 'authorize_capture';
        return 'authorize_capture';
    }

    /**
     * Check if debug mode is enabled for HPP
     * 
     * Used throughout the HPP implementation to conditionally enable
     * debug logging and additional validation.
     * 
     * @param string|null $storeId Store ID for scope-specific configuration
     * @return bool True if debug mode is enabled, false otherwise
     */
    public function isDebugEnabled(?string $storeId = null): bool
    {
        return (bool) $this->getValue('debug', $storeId);
    }

    /**
     * Check if 3D Secure is enabled for HPP
     * 
     * @param string|null $storeId Store ID for scope-specific configuration
     * @return bool True if 3D Secure is enabled, false otherwise
     */
    public function isThreeDSecureEnabled(?string $storeId = null): bool
    {
        return (bool) $this->getValue('enable_three_d_secure', $storeId);
    }
}
