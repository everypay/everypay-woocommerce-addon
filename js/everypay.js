var $checkout_form = '';
function init_everypay(rand_id) {

    $checkout_form = jQuery('.page form.woocommerce-checkout');
    var form_id = $checkout_form.attr('id');

    if (typeof form_id !== typeof undefined && form_id !== false) {
        //do nothing
    } else {
        //add id to the form
        form_id = 'checkout_form_everypay'
        $checkout_form.attr('id', form_id);
    }    
    
    EverypayButton.jsonInit(EVERYPAY_OPC_BUTTON, '#' + form_id);
}

open_everypay_button = function(){
    $checkout_form.find('.everypay-button').trigger('click');
}

handleCallback = function (message) {
    $checkout_form.append('<input type="hidden" value="' + message.token + '" name="everypayToken">');    
    $checkout_form.submit();
    $checkout_form.prepend('<h5>Submitting form.Please wait...</h5>');    
};

