var EVDATA;


var calculate_installments = function (max_installments) {
    var installments = [];
    var y = 2;
    for (let i = 2; i <= max_installments; i += y) {
        if (i >= 12)
            y = 12;

        installments.push(i);
    }
    return installments;
}


function load_everypay() {

    payFormElement =  document.getElementById('pay-form');

    var payload = {
        pk: EVDATA.pk,
        amount: EVDATA.amount,
        locale: EVDATA.locale,
        data: {
            billing: {
                addressLine1: EVDATA.billing_address
            }
        },
        txnType: 'tds',
        theme:'default',
    formOptions: {},
    inputOptions: {},
    errorOptions: {}
};

    if (EVDATA.max_installments)
        payload.installments = calculate_installments(EVDATA.max_installments);

    function handleResponse(api) {
        if (api.response === 'success') {
            handleCallback(api)
        }

        if (api.onLoad == true) {
            closeEverypayLoadingScreen();
            document.querySelector('.tingle-modal ').style.visibility = "visible";
            document.getElementById('pay-form').style.visibility = "visible";
        }

    }
    everypay.payform(payload, handleResponse);
    window.everypay_modal.open();
}


handleCallback = function (message) {
    var checkout_form = jQuery('form[name="checkout"]');

    checkout_form.append('<input type="hidden" value="' + message.token + '" name="everypayToken">');
    enableEverypayLoadingScreen();

    try{
        checkout_form.submit();
    } catch(err){
        checkout_form.find('#place_order').trigger('click');
    }   
};

(function( $ ) {
    "use strict";
    $('body').on('change', 'input[name="payment_method"]', function() { $('body').trigger('update_checkout'); });
})(jQuery);


