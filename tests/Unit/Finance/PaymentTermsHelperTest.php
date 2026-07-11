<?php

namespace Tests\Unit\Finance;

use App\Services\Finance\PaymentTermsHelper;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

/**
 * Pure-logic unit test (no database) for the shared credit-term → due-date
 * helper used by Storage, Handling, Repair, Reefer and General invoices.
 */
class PaymentTermsHelperTest extends TestCase
{
    public function test_due_date_offsets_from_the_invoice_date(): void
    {
        $from = Carbon::parse('2026-01-01');

        $this->assertSame('2026-01-01', PaymentTermsHelper::dueDate('cod', $from)->toDateString());
        $this->assertSame('2026-01-16', PaymentTermsHelper::dueDate('net15', $from)->toDateString());
        $this->assertSame('2026-01-31', PaymentTermsHelper::dueDate('net30', $from)->toDateString());
        $this->assertSame('2026-02-15', PaymentTermsHelper::dueDate('net45', $from)->toDateString());
        $this->assertSame('2026-03-02', PaymentTermsHelper::dueDate('net60', $from)->toDateString());
    }

    public function test_unknown_term_falls_back_to_net30(): void
    {
        $from = Carbon::parse('2026-01-01');

        $this->assertSame('2026-01-31', PaymentTermsHelper::dueDate('nonsense', $from)->toDateString());
    }

    public function test_labels(): void
    {
        $this->assertSame('Cash on Delivery', PaymentTermsHelper::label('cod'));
        $this->assertSame('Net 30 Days', PaymentTermsHelper::label('net30'));
        $this->assertSame('net99', PaymentTermsHelper::label('net99')); // unknown → raw
    }
}
