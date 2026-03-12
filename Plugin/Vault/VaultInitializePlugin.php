<?php
/**
 * Plugin to disable initialize flow for vault payments.
 *
 * The Magento Vault class doesn't implement initialize() but delegates
 * isInitializeNeeded() to the vault provider. When the provider has
 * can_initialize=1, vault payments fail with "Not implemented" error.
 *
 * This plugin ensures vault payments use the standard authorize/capture
 * flow instead of the initialize flow.
 */
declare(strict_types=1);

namespace GlobalPayments\PaymentGateway\Plugin\Vault;

use Magento\Vault\Model\Method\Vault;

class VaultInitializePlugin
{
    /**
     * Force vault payments to not use initialize flow.
     *
     * @param Vault $subject
     * @param bool $result
     * @return bool
     */
    public function afterIsInitializeNeeded(Vault $subject, bool $result): bool
    {
        // Vault payments should never use initialize flow
        // as Magento\Vault\Model\Method\Vault::initialize() is not implemented
        return false;
    }
}