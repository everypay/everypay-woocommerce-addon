import { describe, expect, it } from 'vitest';

const {
    calculateAmount,
    calculateInstallments,
} = require('../../assets/js/blocks/everypay-blocks-utils.js');

describe('everypay blocks utils', () => {
    it('keeps cart totals in minor units unchanged when provided as whole-number strings', () => {
        expect(calculateAmount('15000', 2, false)).toBe(15000);
    });

    it('scales installment thresholds expressed as whole numbers to minor units', () => {
        expect(calculateAmount('100', 2, true)).toBe(10000);
        expect(calculateAmount('500', 2, true)).toBe(50000);
    });

    it('calculates installments against normalized threshold values', () => {
        const installmentConfig = JSON.stringify([
            { from: '100', to: '500', max: '6' },
            { from: '500.01', to: '1000', max: '12' },
        ]);

        expect(calculateInstallments(15000, installmentConfig)).toBe(6);
        expect(calculateInstallments(75000, installmentConfig)).toBe(12);
        expect(calculateInstallments(5000, installmentConfig)).toBe(0);
    });
});
