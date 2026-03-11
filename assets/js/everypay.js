var meta = document.createElement('meta');
meta.httpEquiv = "X-UA-Compatible";
meta.content = "IE=edge";
document.getElementsByTagName('head')[0].appendChild(meta);
var EVDATA;
var modal = new EverypayModal(EVDATA);

function createEverypayModal(everypayData) {
    if (modal && typeof modal.destroy === 'function') {
        modal.destroy();
    }

    modal = new EverypayModal(everypayData);
    return modal;
}

var payformResponseHandler = function(response) {

    if (response && response.response && response.response === 'success') {
        hideEverypayError();

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

    if (response && response.response && response.response === 'error' && response.error) {
        showEverypayError(response.error);

        setTimeout(function () {
            hideEverypayError();
            if (modal) {
                modal.hide_loading();
                modal.close();
            }
        }, 4000);
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

function executeEverypayPayment(everypayData, options) {
    var settings = options || {};
    var currentModal = createEverypayModal(everypayData);
    var isSettled = false;

    currentModal.show_loading();
    currentModal.onClose = function() {
        if (isSettled) {
            return;
        }
    };

    var payload = create_payload(everypayData);
    if (!payload) {
        currentModal.hide_loading();
        return Promise.reject(new Error("An error occurred. Please try again."));
    }

    return new Promise(function(resolve, reject) {
        currentModal.onClose = function() {
            if (isSettled) {
                return;
            }

            isSettled = true;
            hideEverypayError();
            reject(new Error("Payment canceled."));
        };

        var responseHandler = function(response) {
            if (response && response.response && response.response === 'success') {
                isSettled = true;
                hideEverypayError();
                currentModal.destroy();
                resolve(response.token);
                return;
            }

            if (response && response.response && response.response === 'error' && response.error) {
                isSettled = true;
                showEverypayError(response.error);

                setTimeout(function () {
                    currentModal.hide_loading();
                    currentModal.close({ skipConfirm: true, silent: true });
                }, 4000);

                reject(new Error(response.error));
                return;
            }

            if (response.onLoad == true) {
                if (settings.showSaveCardUi && everypayData.save_cards) {
                    currentModal.show_save_card();
                }

                currentModal.hide_loading();
                currentModal.open();
            }
        };

        if (everypayData.tokenized) {
            var tokenized_card = document.querySelector('input[name="tokenized-card"]:checked');
            if (!tokenized_card || !checkIfTokenizedCardHasTheRequiredFields(tokenized_card)) {
                currentModal.hide_loading();
                reject(new Error("An error occurred. Please try again."));
                return;
            }

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

            everypay.tokenized(payload, responseHandler);
            return;
        }

        everypay.payform(payload, responseHandler);
    });
}

function load_everypay() {
    executeEverypayPayment(EVDATA, { showSaveCardUi: true }).then(function(token) {
        if (document.querySelector('input[name="everypayToken"]')) {
            document.querySelector('input[name="everypayToken"]').remove();
        }

        var checkout_form = document.querySelector('form[name="checkout"]');
        var placeOrderButton = document.querySelector("#place_order");

        if (!checkout_form || !placeOrderButton) {
            return;
        }

        var tokenInput = document.createElement('input');
        tokenInput.setAttribute('type', 'hidden');
        tokenInput.setAttribute('id', 'everypayTokenInput');
        tokenInput.setAttribute('name', 'everypayToken');
        tokenInput.setAttribute('value', token);
        checkout_form.appendChild(tokenInput);

        modal.show_loading();
        setTimeout(function() {
            modal.hide_loading();
        }, 4000);

        setTimeout(function() {
            placeOrderButton.click();
        }, 350);
    }).catch(function(error) {
        if (error && error.message && error.message !== "Payment canceled.") {
            showEverypayError(error.message);
        }
    });
}

window.everypayStartPayment = executeEverypayPayment;

var showEverypayError = function (message) {
    var existing = document.getElementById('everypay-error');
    if (!existing) {
        var container = document.createElement('div');
        container.id = 'everypay-error';
        container.className = 'woocommerce-error';
        container.style.marginBottom = '1em';

        var checkoutForm = document.querySelector('form.woocommerce-checkout');
        if (checkoutForm) {
            checkoutForm.insertBefore(container, checkoutForm.firstChild);
            existing = container;
        }
    }

    if (existing) {
        existing.innerText = message || 'IRIS payment failed. Please try another payment method.';
        existing.style.display = 'block';
    }
};

var hideEverypayError = function () {
    var existing = document.getElementById('everypay-error');
    if (existing) {
        existing.style.display = 'none';
    }
};
