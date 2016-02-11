var $checkout_form;
var EVERYPAY_OPC_BUTTON;

function load_everypay() {
    var loadButton = setInterval(function () {
        try {
            $checkout_form = jQuery('form[name="checkout"]');
            EverypayButton.jsonInit(EVERYPAY_OPC_BUTTON, $checkout_form);

            var triggerButton = setInterval(function () {
                try {
                    $checkout_form.find('.everypay-button').trigger('click');
                    clearInterval(triggerButton);
                } catch (err) {
                }
            });

            clearInterval(loadButton);
        } catch (err) {
            console.log(err);
        }
    }, 301);
}

handleCallback = function (message) {
    $checkout_form.append('<input type="hidden" value="' + message.token + '" name="everypayToken">');
    $checkout_form.find('#place_order').trigger('click');
    $checkout_form.prepend('<div class="woocommerce-info">Oλοκλήρωση παραγγελίας. Παρακαλούμε περιμένετε...</a></div>');
};

(function( $ ) {
    "use strict";
    $('body').on('change', 'input[name="payment_method"]', function() { $('body').trigger('update_checkout'); });
})(jQuery);
