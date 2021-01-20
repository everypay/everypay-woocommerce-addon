var EVDATA;

let modal = new EverypayModal(EVDATA);
let payformResponseHandler = (response) => {

    if (response.response === 'success') {
        let checkout_form = jQuery('form[name="checkout"]');
        if (!checkout_form) {
            window.reload();
        }
        try {
            modal.destroy();
            modal.show_loading();
            checkout_form.append('<input type="hidden" value="' + response.token + '" name="everypayToken">');s
            checkout_form.submit();
        } catch(err){
            checkout_form.find('#place_order').trigger('click');
        }
    }

    if (response.onLoad == true) {
        if (EVDATA.save_cards) {
            modal.createSaveCardCheckbox();
        }
        modal.hide_loading();
        modal.open();
    }
};

function load_everypay() {

    modal.show_loading();
    let payload = create_payload(EVDATA);

    if (EVDATA.tokenized) {
        var tokenized_card = jQuery('input[name="tokenized-card"]:checked');
        if (!tokenized_card) {
            window.reload();
        }
        payload.data = {
            cardToken: tokenized_card.attr('crd'),
            cardType: tokenized_card.attr('card_type'),
            cardExpMonth: tokenized_card.attr('exp_month'),
            cardExpYear: tokenized_card.attr('exp_year'),
            cardLastFour: tokenized_card.attr('last_four')
        };
        if (document.getElementById('everypay-save-card-box')) {
            document.getElementById('everypay-save-card-box').remove();
        }
        everypay.tokenized(payload, payformResponseHandler);
    } else {
        everypay.payform(payload, payformResponseHandler);
    }

}


let handleCallback = function (message) {
    let checkout_form = jQuery('form[name="checkout"]');
    try {
        checkout_form.append('<input type="hidden" value="' + message.token + '" name="everypayToken">');
        modal.destroy();
        checkout_form.submit();
    } catch(err){
        checkout_form.find('#place_order').trigger('click');
    }   
};
