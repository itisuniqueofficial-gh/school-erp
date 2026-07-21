<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database\Model;
use Core\Database\QueryBuilder;

/**
 * Fee invoice model.
 */
final class FeeInvoice extends Model
{
    protected string $table = 'fee_invoices';
    protected array $fillable = [
        'invoice_no', 'student_id', 'fee_structure_id', 'academic_year_id',
        'total_amount', 'discount_amount', 'fine_amount', 'paid_amount', 'due_date', 'status',
    ];

    /** @return array<int,array<string,mixed>> Invoices for a student. */
    public static function forStudent(int $studentId): array
    {
        return QueryBuilder::table('fee_invoices')
            ->where('student_id', $studentId)->orderBy('id', 'DESC')->get();
    }

    public static function generateInvoiceNo(): string
    {
        $count = 0;
        try {
            $count = QueryBuilder::table('fee_invoices')->count();
        } catch (\Throwable) {
        }
        return sprintf('INV-%s-%05d', date('Y'), $count + 1);
    }

    /**
     * Recompute paid_amount + status for an invoice from its payments.
     */
    public static function recalculate(int $invoiceId): void
    {
        $invoice = QueryBuilder::table('fee_invoices')->where('id', $invoiceId)->first();
        if ($invoice === null) {
            return;
        }
        $paid = (float) (QueryBuilder::table('fee_payments')
            ->where('fee_invoice_id', $invoiceId)->where('status', 'success')
            ->selectRaw('COALESCE(SUM(amount),0) AS t')->value('t') ?? 0);

        $net = (float) $invoice['total_amount'] + (float) $invoice['fine_amount'] - (float) $invoice['discount_amount'];
        $status = 'unpaid';
        if ($paid >= $net && $net > 0) {
            $status = 'paid';
        } elseif ($paid > 0) {
            $status = 'partial';
        }

        QueryBuilder::table('fee_invoices')->where('id', $invoiceId)->update([
            'paid_amount' => $paid, 'status' => $status, 'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
