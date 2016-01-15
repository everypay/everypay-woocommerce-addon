var $checkout_form;

function init_everypay() {
    $checkout_form = jQuery('form[name="checkout"]');    
    EverypayButton.jsonInit(EVERYPAY_OPC_BUTTON, $checkout_form);
}

open_everypay_button = function(){
    $checkout_form.find('.everypay-button').trigger('click');
}

handleCallback = function (message) {
    $checkout_form.append('<input type="hidden" value="' + message.token + '" name="everypayToken">');    
    $checkout_form.submit();
    $checkout_form.prepend('<div class="woocommerce-info">Submitting form.Please wait...</a></div>');    
};

