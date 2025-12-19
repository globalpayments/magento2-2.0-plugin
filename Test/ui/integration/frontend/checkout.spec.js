/// <reference types="cypress" />

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
  findACategory,
  useASavedCard,
} from '../../support/helpers';

context('Frontend - Checkout', () => {
  beforeEach(() => {
    cy.visit(getStorefrontUrl());
  });

  it('can checkout as guest successfully', () => {
    findACategory();
    addAProductToCart();
    goToCheckout();
    checkoutAsGuest();
    enterBillingInformation();
    confirmShippingMethod();
    enterPaymentInformation({
      number: '4111111111111111',
      expDate: '12 / 2025',
      cvv: '999',
    });
    placeOrder();
    confirmSuccessfulCheckout();
  });

  it('will alert errors during checkout', () => {
    findACategory();
    addAProductToCart();
    goToCheckout();
    checkoutAsGuest();
    enterBillingInformation();
    confirmShippingMethod();
    enterPaymentInformation({
      number: '4111111111111111',
      expDate: '12 / 2025',
      cvv: '123',
    });

    cy.on('window:alert', (message) => {
      expect(message).to.contain('Unexpected Gateway Response');
    });

    placeOrder();
  });

  it('can checkout as customer successfully', () => {
    findACategory();
    addAProductToCart();
    goToCheckout();
    checkoutAsCustomer();
    enterBillingInformation({ isCustomer: true });
    confirmShippingMethod();
    enterPaymentInformation({
      number: '4111111111111111',
      expDate: '12 / 2025',
      cvv: '999',
    });
    placeOrder();
    confirmSuccessfulCheckout();
  });

  it('lets a customer save a card', () => {
    findACategory();
    addAProductToCart();
    goToCheckout();
    checkoutAsCustomer();
    enterBillingInformation({ isCustomer: true });
    confirmShippingMethod();
    enterPaymentInformation({
      number: '4111111111111111',
      expDate: '12 / 2025',
      cvv: '999',
      save: true,
      customerNewCard: true,
    });
    placeOrder();
    confirmSuccessfulCheckout();
  });

  it('lets a customer use a saved card', () => {
    findACategory();
    addAProductToCart();
    goToCheckout();
    checkoutAsCustomer();
    enterBillingInformation({ isCustomer: true });
    confirmShippingMethod();
    useASavedCard();
    placeOrder();
    confirmSuccessfulCheckout();
  });
});
