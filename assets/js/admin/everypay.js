jQuery(document).ready(function ($) {
    function createElements() {
        Mustache.parse(row);

        for (var i = 0, l = installments.length; i < l; i++) {
            var element = installments[i];
            var renderedRow = Mustache.render(row, element);
            $row = $(renderedRow);
            $row.find('input').change(function (e) {
                addInstallment($(this).parent().parent());
            });
            $('#installments table tbody').append($row);
            $row.find('.remove-installment').click(function (e) {
                e.preventDefault();
                removeInstallment($(this).parent().parent());
                $(this).parent().parent().remove();
            });
        }
    }

    var installments = [];
    var row = "<tr data-id=\"{{id}}\">"
            + "<td><input type=\"number\" step=\"0.01\" min=\"0\" name=\"amount_{{id}}_from\" value=\"{{from}}\" class=\"form-control\" /></td>"
            + "<td><input type=\"number\" step=\"0.01\" min=\"0\" name=\"amount_{{id}}_to\" value=\"{{to}}\" class=\"form-control\" /></td>"
            + "<td><input type=\"number\" step=\"1\" max=\"72\" min=\"0\" name=\"max_{{id}}\" value=\"{{max}}\" class=\"form-control\" /></td>"
            + "<td><a class=\"remove-installment\" href=\"#\" title=\"Remove entry\">&times;</a></td>"
            + "</tr>";

    var table = $('#installment-table').html();
    Mustache.parse(table);
    var renderedTable = Mustache.render(table, {});
    $('#installments').html(renderedTable);

    var input = $('#woocommerce_everypay_everypay_maximum_installments').val();
    if (input) {
        //console.log(input);
        installments = JSON.parse(input);
        createElements();
    }

    $('#add-installment').click(function (e) {

        e.preventDefault();
        var maxRows = maxElementIndex();

        Mustache.parse(row);
        var element = {id: maxRows, from: 0, to: 100, max: 12};
        var renderedRow = Mustache.render(row, element);

        $row = $(renderedRow);

        var max = findMaxAmount();

        $row.find("input[name$='from']").val((max + 0.01).toFixed(2))
        $row.find("input[name$='to']").val((parseInt(max.toFixed(0)) + 50).toFixed(2))

        addInstallment($row);

        $row.find('input').change(function (e) {
            addInstallment($(this).parent().parent());
        });

        $('#installments table tbody').append($row);
        $row.find('.remove-installment').click(function (e) {
            e.preventDefault();
            removeInstallment($(this).parent().parent());
            $(this).parent().parent().remove();
        });
    });

    var addInstallment = function (row) {
        var element = {
            id: row.attr('data-id'),
            from: row.find('input[name$="from"]').val(),
            to: row.find('input[name$="to"]').val(),
            max: row.find('input[name^="max"]').val(),
        };

        index = elementExists(element.id);
        if (false !== index) {
            installments[index] = element;
        } else {
            installments.push(element);
        }
        $('#woocommerce_everypay_everypay_maximum_installments').val(JSON.stringify(installments));
    };

    var removeInstallment = function (row) {
        var index = false;
        var id = row.attr('data-id');
        for (var i = 0, l = installments.length; i < l; i++) {
            if (installments[i].id == id) {
                index = i;
            }
        }

        if (false !== index) {
            installments.splice(index, 1);
        }
        $('#woocommerce_everypay_everypay_maximum_installments').val(JSON.stringify(installments));
    };

    var elementExists = function (id) {
        for (var i = 0, l = installments.length; i < l; i++) {
            if (installments[i].id == id) {
                return i;
            }
        }

        return false;
    }

    var maxElementIndex = function (row) {
        var length = $('#installments table tbody tr').length;
        if (0 == length) {
            return 1;
        }

        length = $('#installments table tbody tr:last').attr('data-id');
        length = parseInt(length);

        return length + 1;
    }

    var findMaxAmount = function () {
        var max = 0;
        for (var i = 0, l = installments.length; i < l; i++) {
            if (parseFloat(installments[i].to) > parseFloat(max)) {
                max = parseFloat(installments[i].to)
            }
        }

        return max;
    }


    // -------- The extra fess section ------- //
    $('.everypay-fee-percentage').attr('step', '0.1');
    $('.everypay-fee-fixed').attr('step', '0.01');
    $extra_fee = $('#woocommerce_everypay_everypay_fee_enabled');
    function show_hide_extra_fees() {
        var $trs = $('.everypay-fee-percentage, .everypay-fee-fixed').parents('tr')
        if ($extra_fee.is(":checked"))
        {
            $trs.show()
        } else {
            $trs.hide()
        }
    }
    
    $extra_fee.bind('change', function(){
        show_hide_extra_fees()
    })
    
    //trigger init
    show_hide_extra_fees();

    function toggleGooglePayFields() {
        const enabled = $('#woocommerce_everypay_everypay_googlepay_enabled').is(':checked');
        $('#googlepay-warning').toggle(enabled);
        $('#woocommerce_everypay_everypay_googlepay_country_code').closest('tr').toggle(enabled);
        $('#woocommerce_everypay_everypay_googlepay_merchant_name').closest('tr').toggle(enabled);
        $('#woocommerce_everypay_everypay_googlepay_allowed_card_networks').closest('tr').toggle(enabled);
        $('#woocommerce_everypay_everypay_googlepay_merchant_url').closest('tr').toggle(enabled);
        $('#woocommerce_everypay_everypay_googlepay_allowed_auth_methods').closest('tr').toggle(enabled);
    }

    toggleGooglePayFields();
    $('#woocommerce_everypay_everypay_googlepay_enabled').on('change', toggleGooglePayFields);

});


