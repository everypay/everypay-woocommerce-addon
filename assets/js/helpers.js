
var create_payload = function(everypayData) {
   var payload = {
        pk: everypayData.pk,
        amount: everypayData.amount,
        iframeSource: "Woocommerce CMS - " + everypayData.woocommerce_version,
        display: {
            staticCardPlaceholder: true
        }
    };

   if (everypayData.locale && typeof everypayData.locale === 'string') {
       payload.locale = everypayData.locale;
   }

    if (everypayData.billing_address && typeof everypayData.billing_address === 'string') {
        payload.data = {
            billing: {
                addressLine1: everypayData.billing_address
            },
        }
    }

    if (everypayData.phone && typeof everypayData.phone === 'string') {
        payload.data = {
            ...payload.data,
            phone: everypayData.phone,
        }
    }

    if (everypayData.email && typeof everypayData.email === 'string') {
        payload.data = {
            ...payload.data,
            email: everypayData.email,
        }
    }

    if (everypayData.max_installments
        && typeof everypayData.max_installments === 'number'
        && everypayData.max_installments > 1
    ) {
        payload.installments = calculate_installments(everypayData.max_installments);
    }

    if (Boolean(everypayData.googlePay)) {
        payload.otherPaymentMethods = { googlePay: { ...everypayData.googlePay }};
    }

    if (Boolean(everypayData.applePay)) {
        payload.otherPaymentMethods = { ...payload.otherPaymentMethods, applePay: { ...everypayData.applePay }};
    }

    return payload;
};

var calculate_installments = function (max_installments) {
    var installments = [];
    var y = 2;
    for (var i = 2; i <= max_installments; i += y) {
        if (i >= 12) {
            y = 12;
        }
        installments.push(i);
    }
    return installments;
};


var removeToken = function () {
    if (document.querySelector('input[name="everypayToken"]')) {
        document.querySelector('input[name="everypayToken"]').remove();
    }
};

