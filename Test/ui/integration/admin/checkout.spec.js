/// <reference types="cypress" />

import {
  getStorefrontUrl,
  adminLogin,
  enterInIframe
} from '../../support/helpers';

context('Admin - Checkout', () => {
  beforeEach(() => {
    cy.visit(getStorefrontUrl().trim('/') + '/admin');
  });

  it('can create a customer order', () => {
    adminLogin();

    // goto orders
    cy.get('#nav > li:nth-of-type(2) > ul li.level1:nth-of-type(1) a').click({ force: true });

    // new order
    cy.get('button[title="Create New Order"]:first').click();

    // select a customer
    cy.get('#sales_order_create_customer_grid_table tbody tr:first').click();

    // add a product
    // cy.get('#order-items button.add').click();
    // cy.get('#sales_order_create_search_grid_table tbody tr:first').click();

    // cy.wait(500);

    // cy.get('button[title="Add Selected Product(s) to Order"]').click();
    cy.get('#sidebar_data_reorder input[type="checkbox"]:first').click();
    // cy.window().then((win) => win.order.sidebarApplyChanges());
    cy.wait(2000);
    cy.get('.create-order-sidebar-container button[title="Update Changes"]:first').click();

    cy.wait(2000);

    // select shipping method
    cy.window().then((win) => win.order.loadShippingRates());
    cy.get('#s_method_flatrate_flatrate').check();

    // enter payment details
    cy.window().then((win) => win.payment.switchMethod('hps_transit'));
    cy.get('#p_method_hps_transit').click({force: true});

    cy.wait(2000);

    cy.get("#hps_transit_cc_number_iframe > iframe").then(enterInIframe('4242424242424242'));
    cy.get("#hps_transit_cc_exp_iframe > iframe").then(enterInIframe('12 / 2025'));
    cy.get("#hps_transit_cc_cvv_iframe > iframe").then(enterInIframe('999'));

    cy.wait(1000);

    // submit order
    cy.window().then((win) => win.order.submit());

    // confirm success
    cy.get('#messages .success-msg').should('have.text', 'The order has been created.');
  });
});
