
let create_payload = (everypayData, displayPayButton = true) => {
   let payload = {
        pk: everypayData.pk,
        amount: everypayData.amount,
        locale: everypayData.locale,
        data: {
            billing: { addressLine1: everypayData.billing_address }
        },
        display: { button: displayPayButton }
    };

    if (everypayData.max_installments) {
        payload.installments = calculate_installments(everypayData.max_installments);

    }
    return payload;
};

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


