<?php

namespace GlobalPayments\PaymentGateway\Block\Adminhtml\System\Config\Form\Field;

use Magento\Framework\Data\Form\Element\Checkboxes as BaseCheckboxes;

class Checkboxes extends BaseCheckboxes
{
    /**
     * Retrieve HTML
     *
     * @return string
     */
    public function getElementHtml()
    {
        $values = $this->_prepareValues();
        $validators = $this->getData('field_config')['validate'] ?? '';

        if (!$values) {
            return '';
        }

        $html = '<div class="nested ' . $validators . '">';
        foreach ($values as $value) {
            $html .= $this->_optionToHtml($value);
        }
        $html .= '</div>' . $this->getAfterElementHtml();

        return $html;
    }

    /**
     * @inheritDoc
     */
    protected function _optionToHtml($option)
    {
        /**
         * Prevent the loop to stack '[]' at the end of the name.
         */
        if (!$this->endsWith($this->getName(), '[]')) {
            $this->setName($this->getName() . '[]');
        }

        return parent::_optionToHtml($option);
    }

    /**
     * @inheritDoc
     */
    public function getDisabled($value)
    {
        if ($this->getData('disabled')) {
            return 'disabled';
        }

        return null;
    }

    /**
     *  Determine if a given string ends with a given substring.
     *
     * @param string $haystack
     * @param string $needle
     * @return bool
     */
    private function endsWith($haystack, $needle)
    {
        // @TODO: Remove this method once we drop PHP 7 support and use 'str_ends_with' instead
        $length = strlen($needle);

        if (!$length) {
            return true;
        }

        return substr($haystack, -$length) === $needle;
    }
}
