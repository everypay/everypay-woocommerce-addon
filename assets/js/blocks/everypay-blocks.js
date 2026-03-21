(function () {
    var settings = window.wc && window.wc.wcSettings
        ? window.wc.wcSettings.getSetting('everypay_data', {})
        : {};

    if (!window.wc || !window.wc.wcBlocksRegistry || !window.everypayStartPayment || !settings.publicKey) {
        return;
    }

    var registerPaymentMethod = window.wc.wcBlocksRegistry.registerPaymentMethod;
    var createElement = window.wp.element.createElement;
    var useEffect = window.wp.element.useEffect;
    var decodeEntities = window.wp.htmlEntities.decodeEntities;
    var __ = window.wp.i18n.__;
    var blocksUtils = window.everypayBlocksUtils || {};
    var calculateAmount = blocksUtils.calculateAmount || function () { return 0; };
    var calculateInstallments = blocksUtils.calculateInstallments || function () { return 0; };

    function getBillingAddress(props) {
        if (props.billing && props.billing.billingAddress) {
            return props.billing.billingAddress;
        }

        if (props.billing && props.billing.billingData) {
            return props.billing.billingData;
        }

        return {};
    }

    function getCartTotal(props) {
        if (props.billing && props.billing.cartTotal) {
            return props.billing.cartTotal;
        }

        return {};
    }

    function getEverypayData(props) {
        var billingAddress = getBillingAddress(props);
        var cartTotal = getCartTotal(props);
        var amount = calculateAmount(cartTotal.value || '0', cartTotal.currency_minor_unit);
        var data = {
            pk: settings.publicKey,
            amount: amount,
            locale: settings.locale,
            billing_address: billingAddress.address_1 || '',
            email: billingAddress.email || '',
            phone: billingAddress.phone || '',
            woocommerce_version: settings.woocommerceVersion,
            max_installments: calculateInstallments(amount, settings.installmentConfig),
        };

        if (settings.googlePay) {
            data.googlePay = settings.googlePay;
        }

        if (settings.applePay) {
            data.applePay = settings.applePay;
        }

        if (settings.iris) {
            data.iris = {
                merchantName: settings.iris.merchantName,
                callbackUrl: settings.iris.callbackUrl,
                country: settings.iris.country,
                ajaxUrl: settings.iris.ajaxUrl,
                nonce: settings.iris.nonce,
                action: settings.iris.action,
                isSandbox: settings.iris.isSandbox,
                amount: amount,
                currency: cartTotal.currency_code || '',
                md: '',
            };
        }

        return data;
    }

    function EverypayContent(props) {
        var onPaymentProcessing = props.eventRegistration && props.eventRegistration.onPaymentProcessing;
        var responseTypes = props.emitResponse && props.emitResponse.responseTypes;

        useEffect(function () {
            if (!onPaymentProcessing || !responseTypes) {
                return function () {};
            }

            return onPaymentProcessing(function () {
                if (props.activePaymentMethod !== settings.name) {
                    return {
                        type: responseTypes.SUCCESS,
                    };
                }

                return window.everypayStartPayment(getEverypayData(props), { showSaveCardUi: false }).then(function (token) {
                    var paymentMethodData = {
                        everypayToken: token,
                    };

                    if (props.shouldSavePayment) {
                        paymentMethodData.everypay_save_card = 'save_card';
                    }

                    return {
                        type: responseTypes.SUCCESS,
                        meta: {
                            paymentMethodData: paymentMethodData,
                        },
                    };
                }).catch(function (error) {
                    return {
                        type: responseTypes.ERROR,
                        message: error && error.message ? error.message : __('Everypay could not initialize the payment form.', 'everypay'),
                    };
                });
            });
        }, [onPaymentProcessing, props.activePaymentMethod, props.shouldSavePayment, props.billing]);

        return createElement(
            'div',
            { className: 'everypay-blocks-description' },
            decodeEntities(settings.description || __('Pay securely via Everypay after you place the order.', 'everypay'))
        );
    }

    var Label = function () {
        return createElement(
            'span',
            null,
            decodeEntities(settings.title || 'Everypay')
        );
    };

    registerPaymentMethod({
        name: settings.name || 'everypay',
        label: createElement(Label, null),
        content: createElement(EverypayContent, null),
        edit: createElement(EverypayContent, null),
        canMakePayment: function () {
            return !!settings.publicKey;
        },
        ariaLabel: settings.title || 'Everypay',
        supports: settings.supports || {
            features: ['products'],
        },
    });
})();
