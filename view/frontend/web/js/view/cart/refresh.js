define(
    [
        'Magento_Customer/js/customer-data'
    ],
    function (
        customerData
    ) {
        var sections = ['cart'];
        customerData.reload(sections, true);
    }
);
