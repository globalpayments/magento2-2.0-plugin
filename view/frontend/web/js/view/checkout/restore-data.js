define(
    [
        'jquery',
        'Magento_Customer/js/customer-data',
        'jquery/jquery-storageapi'
    ],
    function (
        $,
        customerData
    ) {
        return {
            cacheKey: 'checkout-data',

            getData: function() {
                var data = customerData.get(this.cacheKey)();

                if ($.isEmptyObject(data)) {
                    data = $.initNamespaceStorage('mage-cache-storage').localStorage.get(this.cacheKey);

                    if ($.isEmptyObject(data)) {
                        data = null;
                    }
                }

                return data;
            },

            saveData: function (data) {
                if (data) {
                    customerData.set(this.cacheKey, data);
                }
            }
        }
    }
);
