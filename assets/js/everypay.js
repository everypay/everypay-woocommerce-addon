var meta = document.createElement('meta');
meta.httpEquiv = "X-UA-Compatible";
meta.content = "IE=edge";
document.getElementsByTagName('head')[0].appendChild(meta);
var EVDATA;
var modal = new EverypayModal(EVDATA);

var payformResponseHandler = function(response) {

    if (response && response.response && response.response === 'success') {

        if (modal) {
            modal.destroy();
            modal.show_loading();
            setTimeout(function () {
                modal.hide_loading();
            }, 4000);
        }
        if (document.querySelector('input[name="everypayToken"]')) {
            document.querySelector('input[name="everypayToken"]').remove();
        }

        var checkout_form = document.querySelector('form[name="checkout"]');
        var placeOrderButton = document.querySelector("#place_order");
        if (checkout_form && placeOrderButton
        ) {
            var tokenInput = document.createElement('input');
            tokenInput.setAttribute('type', 'hidden');
            tokenInput.setAttribute('id', 'everypayTokenInput');
            tokenInput.setAttribute('name', 'everypayToken');
            tokenInput.setAttribute('value', response.token);
            checkout_form.appendChild(tokenInput);
            setTimeout(function() {
                placeOrderButton.click();
            }, 350);
        }

    }

    if (response.onLoad == true) {
        if (EVDATA.save_cards) {
            modal.show_save_card();
        }
        modal.hide_loading();
        modal.open();
    }
};

function checkIfTokenizedCardHasTheRequiredFields(tokenizedCard) {
    if (!tokenizedCard.hasAttribute('crd') ||
        !tokenizedCard.hasAttribute('card_type') ||
        !tokenizedCard.hasAttribute('exp_month') ||
        !tokenizedCard.hasAttribute('exp_year') ||
        !tokenizedCard.hasAttribute('last_four')
    ) {
        return false;
    }
    return true;
}

function load_everypay() {
    modal.show_loading();
    var payload = create_payload(EVDATA);
    // @note
    console.log('check', {payload})
    if (!payload) {
        alert("An error occurred. Please try again.");
        modal.hide_loading();
        return;
    }
    if (EVDATA.tokenized) {
        var tokenized_card = document.querySelector('input[name="tokenized-card"]:checked');
        if (!tokenized_card || !checkIfTokenizedCardHasTheRequiredFields(tokenized_card)) {
            alert("An error occurred. Please try again.");
            modal.hide_loading();
            return;
        }

        // @note fix to send billing with tokenized card
        payload.data = {
            ...payload.data,
            cardToken: tokenized_card.getAttribute('crd'),
            cardType: tokenized_card.getAttribute('card_type'),
            cardExpMonth: tokenized_card.getAttribute('exp_month'),
            cardExpYear: tokenized_card.getAttribute('exp_year'),
            cardLastFour: tokenized_card.getAttribute('last_four')
        };
        if (document.getElementById('everypay-save-card-box')) {
            document.getElementById('everypay-save-card-box').remove();
        }
        everypay.tokenized(payload, payformResponseHandler);
    } else{
        everypay.payform(payload, payformResponseHandler);
    }

}


