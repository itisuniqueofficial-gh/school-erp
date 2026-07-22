<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database\Model;
use Core\Database\QueryBuilder;

/**
 * Ledger row for an online fee payment. Status is only ever advanced by
 * server-side verification (see PaymentGatewayService).
 */
final class PaymentTransaction extends Model
{
    protected string $table = 'payment_transactions';

    protected array $fillable = [
        'uid', 'student_id', 'fee_invoice_id', 'gateway', 'gateway_order_id',
        'gateway_payment_id', 'amount', 'currency', 'status', 'method', 'mode',
        'signature_verified', 'meta',
    ];

    /** @return array<int,array<string,mixed>> Recent transactions with student name. */
    public static function recent(int $limit = 50): array
    {
        return QueryBuilder::table('payment_transactions')
            ->select(['payment_transactions.*', 'students.first_name', 'students.last_name'])
            ->leftJoin('students', 'payment_transactions.student_id', '=', 'students.id')
            ->orderBy('payment_transactions.id', 'DESC')
            ->limit($limit)
            ->get();
    }
}
