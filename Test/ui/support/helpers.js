export function getStorefrontUrl() {
  let result = 'http://127.0.0.1:8082';

  if (process && process.env && process.env.STOREFRONT_URL) {
    result = process.env.STOREFRONT_URL;
  }

  return result;
}

export function confirmSuccessfulCheckout() {
  cy.get('body').should('have.class', 'checkout-onepage-success');
}

export function placeOrder() {
  return cy.get('#review-buttons-container button').click();
}

export function enterPaymentInformation(data) {
  if (!data) {
    data = {};
  }

  cy.get('#globalpayments_paymentgateway_transit').click();

  if (data.customerNewCard) {
    cy.get('#hps_transit_stored_card_select_new').check();
  }

  cy.wait(1000);

  cy.get(".credit-card-number-target > iframe").then(enterInIframe(data.number || '4242424242424242'));
  cy.get(".credit-card-expiration-target > iframe").then(enterInIframe(data.expDate || '12 / 2025'));
  if (data.cvv !== false) {
    cy.get(".credit-card-cvv-target > iframe").then(enterInIframe(data.cvv || '123'));
  }

  if (data.save) {
    cy.get('#hps_transit_cc_save_future').check();
  }

  cy.get('.credit-card-submit-target > iframe').then(clickInIframe());
}

export function useASavedCard() {
  cy.get('#p_method_hps_transit').click();
  cy.get('input[name="hps_transit_stored_card_select"]:first').check();
  cy.get('#payment-buttons-container button').click();
}

export function confirmShippingMethod() {
}

export function enterBillingInformation(data) {
  if (!data) {
    data = {};
  }

  if (data.isCustomer) {
    cy.get('#billing-address-select').select('New Address');
  }

  if (!data.isCustomer) {
    typeInputValue('#checkout-step-shipping input[name="username"]:first', data.email || 'jane@smith.com');
  }
  typeInputValue('#checkout-step-shipping input[name="firstname"]', data.firstName || 'Jane');
  typeInputValue('#checkout-step-shipping input[name="lastname"]', data.lastName || 'Smith');
  typeInputValue('#checkout-step-shipping input[name="street[0]"]', data.street1 || '1 Heartland Way');
  cy.get('#checkout-step-shipping select[name="region_id"]').select('Indiana');
  typeInputValue('#checkout-step-shipping input[name="city"]', data.city || 'Jeffersonville');
  typeInputValue('#checkout-step-shipping input[name="postcode"]', data.zip || '47130');
  typeInputValue('#checkout-step-shipping input[name="telephone"]', data.telephone || '5555555555');

  cy.get('#co-shipping-method-form button[type="submit"]').click();
}

export function checkoutAsCustomer() {
  typeInputValue('#login-email', 'jane@smith.com');
  typeInputValue('#login-password', 'Password123');
  cy.get('#checkout-step-login button[type="submit"]').click();
}

export function checkoutAsGuest() {
}

export function goToCheckout() {
  cy.get('.minicart-wrapper .action.showcart').click();
  cy.get('.block-minicart .action.checkout').click({ force: true });
}

export function addAProductToCart(productUrlKey) {
  if (!productUrlKey) {
    cy.wait(500);
    cy.get('.products-grid .item:first').trigger('mouseover');
    cy.get('.products-grid .item:first button.action.tocart').click({ force: true });
    cy.get('.page.messages > div[data-bind="scope: \'messages\'"]').should('include.text', 'You added');
    return;
  }

  cy.visit(getStorefrontUrl() + '/' + productUrlKey + '.html');
  cy.get('button.action.tocart').click();
}

export function findACategory() {
  cy.get('nav.navigation a:first').click();
}

export function typeInputValue(inputSelector, value) {
  cy.get(inputSelector).type('{selectall}' + value);
}

export function enterInIframe(content, selector) {
  if (!selector) {
    selector = "#secure-payment-field";
  }

  return (frame) => {
    cy
      .wrap(frame.contents().find("body"))
      .find(selector)
      .type(content, { force: true });
  };
}

export function clickInIframe(selector) {
  if (!selector) {
    selector = "#secure-payment-field";
  }

  return (frame) => {
    cy
      .wrap(frame.contents().find("body"))
      .find(selector)
      .click({ force: true });
  };
}

export function adminLogin() {
  typeInputValue('#username', 'admin');
  typeInputValue('#login', 'admin123');
  cy.get('#login-form button').click();
}

export function setPaymentAction(action) {
  // goto system config
  cy.get('#nav > li:last-child > ul > li:last-child a').should('have.text', 'Configuration').click({ force: true });

  cy.get('#system_config_tabs dd:nth-of-type(9) a').click();

  cy.get('#payment_hps_transit_payment_action').select(action);

  cy.get('#content .form-buttons button[title="Save Config"]:first').click();
}