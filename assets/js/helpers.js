
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

    if (Boolean(everypayData.iris)) {
        var iris = {
            merchantName: everypayData.iris.merchantName,
            country: everypayData.iris.country,
            callbackUrl: everypayData.iris.callbackUrl,
            md: everypayData.iris.md,
        };

        Object.defineProperty(iris, 'sessionHandler', {
            value: createIrisSessionHandler(everypayData.iris),
            enumerable: false,
            configurable: true,
            writable: true
        });

        payload.otherPaymentMethods = {
            ...payload.otherPaymentMethods,
            iris: iris
        };
    }

    return payload;
};

var calculate_installments = function (max_installments) {
    var installments = [];
    for (var i = 2; i <= max_installments; i++) {
        installments.push(i);
    }
    return installments;
};

var removeToken = function () {
    if (document.querySelector('input[name="everypayToken"]')) {
        document.querySelector('input[name="everypayToken"]').remove();
    }
};

var createIrisSessionHandler = function (irisConfig) {
    return async function (sessionPayload) {
        var body = new URLSearchParams();
        body.append('action', irisConfig.action);
        body.append('_nonce', irisConfig.nonce);

        if (sessionPayload && sessionPayload.uuid) {
            body.append('uuid', sessionPayload.uuid);
        }
        if (sessionPayload && sessionPayload.md) {
            body.append('md', sessionPayload.md);
        }

        if (irisConfig.amount) {
            body.append('amount', irisConfig.amount);
        }

        if (irisConfig.currency) {
            body.append('currency', irisConfig.currency);
        }

        if (irisConfig.md) {
            body.append('md', irisConfig.md);
        }

        if (irisConfig.country) {
            body.append('country', irisConfig.country);
        }

        try {
            var response = await fetch(irisConfig.ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                },
                body: body.toString()
            });

            var json = await response.json();
            if (!json || !json.success || !json.data || !json.data.signature) {
                var message = (json && json.data && json.data.message) ? json.data.message : 'Invalid IRIS session response';
                throw new Error(message);
            }

            return json.data.signature;
        } catch (error) {
            console.error('IRIS session creation failed', error);
            throw error;
        }
    };
};
