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
    
    var img_src = 'data:image/gif;base64,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';
   
    jQuery('body').prepend('<div class="loader-everypay" style="position: fixed;height: 100%;width: 100%;background: #f2f2f2;z-index: 100000;top: 0;left: 0;\n\
opacity: 0.9;"><center style="clear: both;font-size: 1.3em;padding: 40% 0 0;">Oλοκλήρωση παραγγελίας. Παρακαλούμε περιμένετε...<br /><br />\n\
<img src="'+img_src+'"></center></div>');
    
    try{
        $checkout_form.submit();
    } catch(err){
        $checkout_form.find('#place_order').trigger('click');
    }   
};

(function( $ ) {
    "use strict";
    $('body').on('change', 'input[name="payment_method"]', function() { $('body').trigger('update_checkout'); });
})(jQuery);
