/// <reference types="cypress" />

import {
  amex,
  diners,
  discover,
  mastercard2bin,
  mastercardKeyed,
  visa1,
} from '../../support/test-data';
import {
  getStorefrontUrl,
  confirmSuccessfulCheckout,
  placeOrder,
  enterPaymentInformation,
  confirmShippingMethod,
  enterBillingInformation,
  checkoutAsGuest,
  goToCheckout,
  addAProductToCart,
  findACategory,
} from '../../support/helpers';

context('Frontend - Checkout', () => {
  beforeEach(() => {
    cy.visit(getStorefrontUrl());
  });

  it('card not present - internet - tsep visa', () => {
    findACategory();
    addAProductToCart();
    goToCheckout();
    checkoutAsGuest();
    enterBillingInformation();
    // confirmShippingMethod();
    enterPaymentInformation(visa1);
    // placeOrder();
    // confirmSuccessfulCheckout();
  });

  // it('card not present - internet - tsep mastercard', () => {
  //   findACategory();
  //   addAProductToCart();
  //   goToCheckout();
  //   checkoutAsGuest();
  //   enterBillingInformation();
  //   confirmShippingMethod();
  //   enterPaymentInformation(mastercardKeyed);
  //   placeOrder();
  //   confirmSuccessfulCheckout();
  // });

  // it('card not present - internet - tsep mastercard 2 bin', () => {
  //   findACategory();
  //   addAProductToCart();
  //   goToCheckout();
  //   checkoutAsGuest();
  //   enterBillingInformation();
  //   confirmShippingMethod();
  //   enterPaymentInformation(mastercard2bin);
  //   placeOrder();
  //   confirmSuccessfulCheckout();
  // });

  // it('card not present - internet - tsep discover', () => {
  //   findACategory();
  //   addAProductToCart();
  //   goToCheckout();
  //   checkoutAsGuest();
  //   enterBillingInformation();
  //   confirmShippingMethod();
  //   enterPaymentInformation(discover);
  //   placeOrder();
  //   confirmSuccessfulCheckout();
  // });

  // it('card not present - internet - tsep amex', () => {
  //   findACategory();
  //   addAProductToCart();
  //   goToCheckout();
  //   checkoutAsGuest();
  //   enterBillingInformation();
  //   confirmShippingMethod();
  //   enterPaymentInformation(amex);
  //   placeOrder();
  //   confirmSuccessfulCheckout();
  // });

  // it('card not present - internet - tsep diners', () => {
  //   findACategory();
  //   addAProductToCart();
  //   goToCheckout();
  //   checkoutAsGuest();
  //   enterBillingInformation();
  //   confirmShippingMethod();
  //   enterPaymentInformation(diners);
  //   placeOrder();
  //   confirmSuccessfulCheckout();
  // });
});
