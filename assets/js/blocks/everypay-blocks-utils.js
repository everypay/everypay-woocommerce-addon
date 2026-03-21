(function (root, factory) {
    if (typeof module === 'object' && module.exports) {
        module.exports = factory();
        return;
    }

    root.everypayBlocksUtils = factory();
}(typeof globalThis !== 'undefined' ? globalThis : this, function () {
    function calculateAmount(value, currencyMinorUnit, shouldScaleWholeNumbers) {
        if (typeof value === 'number') {
            return value;
        }

        if (typeof value !== 'string') {
            return 0;
        }

        var normalized = value.replace(',', '.');
        var parsed = parseFloat(normalized);

        if (Number.isNaN(parsed)) {
            return 0;
        }

        if (normalized.indexOf('.') === -1 && normalized.indexOf(',') === -1) {
            if (shouldScaleWholeNumbers) {
                var integerMinorUnit = typeof currencyMinorUnit === 'number' ? currencyMinorUnit : 2;
                return parsed * Math.pow(10, integerMinorUnit);
            }

            return parsed;
        }

        var minorUnit = typeof currencyMinorUnit === 'number' ? currencyMinorUnit : 2;
        return Math.round(parsed * Math.pow(10, minorUnit));
    }

    function calculateInstallments(amount, installmentConfig) {
        if (!installmentConfig) {
            return 0;
        }

        var ranges;
        try {
            ranges = JSON.parse(installmentConfig);
        } catch (error) {
            return 0;
        }

        if (!Array.isArray(ranges)) {
            return 0;
        }

        var maxInstallments = 0;

        ranges.forEach(function (range) {
            var fromAmount = calculateAmount(String(range.from || ''), 2, true);
            var toAmount = calculateAmount(String(range.to || ''), 2, true);
            var max = parseInt(range.max, 10);

            if (amount >= fromAmount && amount <= toAmount && max > maxInstallments) {
                maxInstallments = max;
            }
        });

        return maxInstallments;
    }

    return {
        calculateAmount: calculateAmount,
        calculateInstallments: calculateInstallments,
    };
}));
