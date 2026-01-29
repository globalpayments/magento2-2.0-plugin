<a href="https://github.com/globalpayments" target="_blank">
    <img src="https://avatars.githubusercontent.com/u/25797248?s=200&v=4" alt="Global Payments logo" title="Global Payments" align="right" width="225" />
</a>

# Changelog

## v2.3.6 (1/29/26)
### Enhancements:
- Updated Shopping Cart Plugin Admin Settings URL to Regional Support Page
### Bug Fxies:
- GpApi gateway: Drop-in UI now authorises payments correctly

## v2.3.5 (1/15/26)
### Enhancements:
 - Global payments rebranding

## v2.3.4 (1/8/26)
### Enhancements:
 - Updated Composer requirements to latest version of globalpayments/php-sdk
 - GpApi gateway: Improved order status handling for HPP card payments

## v2.3.3 (12/11/25)
### Bug Fixes:
 - Removed global payments logo from HPP success/decline modal

## v2.3.2 (12/04/25)
### Enhancements:
 - Enhanced admin configurations & payer state validation

## v2.3.0 (11/24/25)
### Enhancements:
 - Added Hosted Payment Pages functionality to GPAPI

## v2.2.4 (11/13/25)
### Bug Fixes:
- Fix for challange notification url for 3DS checkout

## v2.2.3 (10/30/25)
### Enhancements:
- Enhanced compatibility for PHP 8.4
- Updated SHA-1 with SHA-256 in getVelocityVarPrefix for improved security

### Bug Fixes:
- GpApi gateway: removed dependency on some non-core modules that have been observed causing bugs

## v2.2.0 (09/04/25)
- Updated GP API gateway to use Drop-In UI
- Added support for Polish APMs (Blik & Bank Select)

## v2.1.13 (05/29/25)
- Fix TSYS-Genius gateway issue concerning incorrect handling of partial authorization responses

## v2.1.12 (04/03/25)
- Added Spanish (MX) translations

## v2.1.11 (02/13/25)
- Added logic to check if the add payment method screen should be enabled based on merchant configuration options

## v2.1.9 (10/31/24)
- Compatibility with Magento / Adobe Commerce 2.4.7 and PHP 8.3

## v2.1.8 (10/10/24)
- Added French translations

## v2.1.7 (10/08/24)
- Updated the HF library to the latest version

## v2.1.6 (08/02/24)
- Hotfix for the v2.1.5 version

## v2.1.5 (07/25/24)
### Enhancements:
- updated psr/log version to 2||3
- updated the Google Pay mark
- updated default payment selection functionality

## v2.1.4 (03/12/24)
### Enhancements:
- Fix admin-panel refund handling for multi-store installations

## v2.1.3 (02/12/24)
### Enhancements:
- Merged Sepa and Faster Payments into Bank Payment
- Added sort order for the payment methods

## v2.1.2 (01/30/24)
### Enhancements:
- Unified Payments - added PayPal
- Heartland - added Google Pay and Apple Pay compatible with the Heartland Payments Gateway

## v2.1.1 (01/11/24)
### Enhancements:
- GPI Transaction - Added GPI Transaction gateway support

## v2.1.0 (11/07/23)
### Enhancements:
- Google Pay / Apple Pay - added the possibility to partial refund an order
- Unified Payments - added Open Banking (Sepa and Faster Payments)

## v2.0.6 (09/10/23)
### Bug Fixes:
- Fixed a bug where the card type and last 4 cc would not be displayed on elsewhere.

## v2.0.5 (08/31/23)
### Enhancements:
- Unified Payments - added the option to enable/disable the 3DS process

### Bug Fixes:
- Fixed a bug where the payment token would not get decoded

## v2.0.4 (08/22/23)
### Enhancements:
- Supplemental to v2.0.3: duplicates v2.0.3 changes in cases where authorization-only trans type is used

## v2.0.3 (08/15/23)
### Enhancements:
- Supplemental to v2.0.2: save last 4 of CC to aforementioned tables when saved payment method is used

## v2.0.2 (08/03/23)
### Enhancements:
- Added functionality to save payment info to additional tables for easier access

## v2.0.1 (07/25/23)
### Enhancements:
- Added the Card Holder Name in the Google Pay and Apple Pay requests

### Bug Fixes:
- Fixed a bug where the cards would be stored for later use, even if the 'Save for later use' checkbox was unchecked

## v2.0.0 (07/13/23)
#### Enhancements:
- Magento 2.4.6 Compatibility
- Dropped PHP 7 and Magento 2.3 support

## v1.10.2 (06/22/23)
#### Enhancements:
- Unified Payments: Added Credential Check button

### Bug Fixes:
- Fixed a bug where the Card Number iframe would not be 100% expanded on Mozilla Firefox

---
