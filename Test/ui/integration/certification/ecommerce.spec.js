/// <reference types="cypress" />

import {
  amex,
  diners,
  discover,
  discoverCup1,
  jcb,
  mastercard2bin,
  mastercardKeyed,
  visa1,
  visa2,
} from '../../support/test-data';
import {
  getStorefrontUrl,
  confirmSuccessfulCheckout,
  placeOrder,
  enterPaymentInformation,
  confirmShippingMethod,
  enterBillingInformation,
  checkoutAsGuest,
  checkoutAsCustomer,
  goToCheckout,
  addAProductToCart,
  setPaymentAction,
  adminLogin,
} from '../../support/helpers';

context('Frontend - Checkout', () => {
  beforeEach(() => {
    cy.visit(getStorefrontUrl());
  });

  it('card not present - internet - sale mastercard 2 bin', () => {
    // 11.10
    addAProductToCart('test-1110');
    goToCheckout();
    checkoutAsGuest();
    enterBillingInformation();
    // confirmShippingMethod();
    enterPaymentInformation(mastercard2bin);
    placeOrder();

    cy.get('#payment_form_hps_transit').should('be.visible');
  });

  it('card not present - internet - sale discover', () => {
    // 12.00
    addAProductToCart('test-1200');
    goToCheckout();
    checkoutAsGuest();
    enterBillingInformation();
    // confirmShippingMethod();
    enterPaymentInformation(discover);
    placeOrder();
    confirmSuccessfulCheckout();
  });

  it('card not present - internet - auth diners', () => {
    // 6.00
    cy.visit(getStorefrontUrl() + '/admin');
    adminLogin();
    setPaymentAction('authorize');
    cy.visit(getStorefrontUrl());

    addAProductToCart('test-600');
    goToCheckout();
    checkoutAsGuest();
    enterBillingInformation();
    // confirmShippingMethod();
    enterPaymentInformation(diners);
    placeOrder();
    confirmSuccessfulCheckout();

    cy.visit(getStorefrontUrl() + '/admin');
    setPaymentAction('authorize_capture');
  });

  it('card not present - internet - sale mastercard', () => {
    // 15.00
    addAProductToCart('test-1500');
    goToCheckout();
    checkoutAsGuest();
    enterBillingInformation();
    // confirmShippingMethod();
    enterPaymentInformation(mastercardKeyed);
    placeOrder();
    confirmSuccessfulCheckout();
  });

  it('card not present - internet - sale mastercard', () => {
    // 34.13
    addAProductToCart('test-3413');
    goToCheckout();
    checkoutAsGuest();
    enterBillingInformation();
    // confirmShippingMethod();
    enterPaymentInformation(mastercardKeyed);
    placeOrder();
    confirmSuccessfulCheckout();
  });

  it('card not present - internet - sale jcb', () => {
    // 13.00
    addAProductToCart('test-1300');
    goToCheckout();
    checkoutAsGuest();
    enterBillingInformation();
    // confirmShippingMethod();
    enterPaymentInformation(jcb);
    placeOrder();
    confirmSuccessfulCheckout();
  });

  it('card not present - internet - sale amex', () => {
    // 13.50
    addAProductToCart('test-1350');
    goToCheckout();
    checkoutAsGuest();
    enterBillingInformation();
    // confirmShippingMethod();
    // const amexNoCvv = Object.assign({}, amex);
    // amexNoCvv.cvv = false;
    enterPaymentInformation(amex);
    placeOrder();
    confirmSuccessfulCheckout();
  });

  it('card not present - internet - sale visa', () => {
    // 32.49
    addAProductToCart('test-3249');
    goToCheckout();
    checkoutAsGuest();
    enterBillingInformation();
    // confirmShippingMethod();
    enterPaymentInformation(visa1);
    placeOrder();
    confirmSuccessfulCheckout();
  });

  it('card not present - internet - sale discover cup', () => {
    // 10.00
    addAProductToCart('test-1000');
    goToCheckout();
    checkoutAsGuest();
    enterBillingInformation();
    // confirmShippingMethod();
    enterPaymentInformation(discoverCup1);
    placeOrder();
    confirmSuccessfulCheckout();
  });

  it('card not present - internet - sale visa', () => {
    // 11.12
    addAProductToCart('test-1112');
    goToCheckout();
    checkoutAsGuest();
    enterBillingInformation();
    // confirmShippingMethod();
    enterPaymentInformation(visa2);
    placeOrder();
    confirmSuccessfulCheckout();
  });

  it('card not present - internet - sale amex', () => {
    // 4.00
    addAProductToCart('test-400');
    goToCheckout();
    checkoutAsGuest();
    enterBillingInformation();
    // confirmShippingMethod();
    enterPaymentInformation(amex);
    placeOrder();
    confirmSuccessfulCheckout();

    // todo void in admin
  });

  // it('card on file - customer initiatied - sale visa', () => {
  //   // 14.00
  //   addAProductToCart('test-1400');
  //   goToCheckout();
  //   checkoutAsCustomer();
  //   enterBillingInformation({ isCustomer: true });
  //   confirmShippingMethod();
  //   // todo select card
  //   enterPaymentInformation(visa2);
  //   placeOrder();
  //   confirmSuccessfulCheckout();
  // });

  // it('card on file - customer initiatied - sale mastercard', () => {
  //   // 15.00
  //   addAProductToCart('test-1500');
  //   goToCheckout();
  //   checkoutAsCustomer();
  //   enterBillingInformation({ isCustomer: true });
  //   confirmShippingMethod();
  //   // todo select card
  //   enterPaymentInformation(mastercardKeyed);
  //   placeOrder();
  //   confirmSuccessfulCheckout();
  // });
});
