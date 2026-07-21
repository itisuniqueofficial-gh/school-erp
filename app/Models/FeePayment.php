<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database\Model;
use Core\Database\QueryBuilder;

/**
 * Fee payment (receipt) model.
 */
final class FeePayment extends Model
{
    protected string $table = 'fee_payments';
    protected array $fillable = [
        'receipt_no', 'fee_invoice_id', 'student_id', 'amount', 'payment_date',
        'method', 'reference', 'transaction_id', 'status', 'collected_by', 'remarks',
    ];

    public static function generateReceiptNo(): string
    {
        $count = 0;
        try {
            $count = QueryBuilder::table('fee_payments')->count();
        } catch (\Throwable) {
        }
        return sprintf('RCPT-%s-%05d', date('Y'), $count + 1);
    }

    /**
     * Detailed receipt with student + invoice info.
     *
     * @return array<string,mixed>|null
     */
    public static function receipt(int $id): ?array
    {
        return QueryBuilder::table('fee_payments')
            ->select([
                'fee_payments.*',
                'students.first_name', 'students.last_name', 'students.admission_no',
                'fee_invoices.invoice_no',
            ])
            ->leftJoin('students', 'fee_payments.student_id', '=', 'students.id')
            ->leftJoin('fee_invoices', 'fee_payments.fee_invoice_id', '=', 'fee_invoices.id')
            ->where('fee_payments.id', $id)
            ->first();
    }
}
