

let deleteCard = (element) => {
    let card = element.parentElement.querySelector('input[name="tokenized-card"]');
    let checkout_form = jQuery('form.woocommerce-checkout #customer_details div .woocommerce-billing-fields');
    if (!card || !card.value || !checkout_form) {
        return;
    }
    var confirm = window.confirm("Are you sure you want to delete your card?");
    if (!confirm) {
        return;
    }

    let checkout_input = document.createElement('input');
    checkout_input.setAttribute('name', 'delete_card');
    checkout_input.innerHTML = card.value;
    checkout_form.append('<input type="hidden" value="' +card.value +'" name="delete_card" />');
    jQuery('#place_order').click();
    if (document.querySelector('input[name="delete_card"]')) {
        document.querySelector('input[name="delete_card"]').remove();
        card.parentElement.parentElement.remove();
    }
}


