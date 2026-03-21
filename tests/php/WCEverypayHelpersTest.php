<?php

use PHPUnit\Framework\TestCase;

final class WCEverypayHelpersTest extends TestCase
{
    private WC_Everypay_Helpers $helpers;

    protected function setUp(): void
    {
        $this->helpers = new WC_Everypay_Helpers();
    }

    public function testFormatAmountScalesWholeNumberStringsToMinorUnits(): void
    {
        $this->assertSame(10000, $this->helpers->format_amount('100'));
    }

    public function testFormatAmountPreservesDecimalStringsAsMinorUnits(): void
    {
        $this->assertSame(10050, $this->helpers->format_amount('100.50'));
    }

    public function testCalculateInstallmentsUsesFormattedThresholds(): void
    {
        $installments = json_encode(
            array(
                array(
                    'from' => '100',
                    'to' => '500',
                    'max' => '6',
                ),
                array(
                    'from' => '500.01',
                    'to' => '1000',
                    'max' => '12',
                ),
            )
        );

        $this->assertSame(6, $this->helpers->calculate_installments(15000, $installments));
        $this->assertSame(12, $this->helpers->calculate_installments(75000, $installments));
        $this->assertSame(0, $this->helpers->calculate_installments(5000, $installments));
    }
}
