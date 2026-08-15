<?php

declare(strict_types=1);

/**
 * Fee-payment integrity regression test.
 *
 * Invariants:
 *   - Net payable = total + fine - discount (FeeInvoice::netAmount).
 *   - Outstanding = net - SUCCESSFUL payments only, never negative
 *     (FeeInvoice::outstanding).
 *   - storePayment validates server-side that the invoice belongs to the
 *     student and the amount does not exceed the outstanding balance
 *     (never trust client-supplied invoice/student/amount).
 */

require_once dirname(__DIR__) . '/bootstrap/app.php';

use App\Models\FeeInvoice;
use Core\Database\QueryBuilder;

$pass = 0; $fail = 0;
$check = static function (string $l, bool $c) use (&$pass, &$fail): void {
    if ($c) { $pass++; } else { $fail++; echo "FAIL  {$l}\n"; }
};

// --- Pure logic: net amount.
$check('netAmount = total + fine - discount', abs(FeeInvoice::netAmount([
    'total_amount' => 1000, 'fine_amount' => 50, 'discount_amount' => 100,
]) - 950.0) < 0.001);

// --- Behavioral: outstanding tracks only successful payments.
$student = QueryBuilder::table('students')->whereNull('deleted_at')->first();
if ($student === null) {
    $check('a seeded student exists for the fee test', false);
} else {
    $sid = (int) $student['id'];
    $invNo = 'TEST-INV-' . substr(md5((string) mt_rand()), 0, 10);
    $invId = QueryBuilder::table('fee_invoices')->insert([
        'invoice_no' => $invNo, 'student_id' => $sid,
        'total_amount' => 1000, 'fine_amount' => 50, 'discount_amount' => 100,
        'paid_amount' => 0, 'status' => 'unpaid',
        'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
    ]);

    $check('fresh invoice outstanding equals net (950)', abs((FeeInvoice::outstanding($invId) ?? -1) - 950.0) < 0.001);

    // A successful payment reduces outstanding.
    QueryBuilder::table('fee_payments')->insert([
        'receipt_no' => 'TEST-RC-' . substr(md5($invNo . 'a'), 0, 8),
        'fee_invoice_id' => $invId, 'student_id' => $sid, 'amount' => 600,
        'payment_date' => date('Y-m-d'), 'method' => 'cash', 'status' => 'success',
        'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
    ]);
    FeeInvoice::recalculate($invId);
    $check('outstanding after 600 success = 350', abs((FeeInvoice::outstanding($invId) ?? -1) - 350.0) < 0.001);
    $inv = QueryBuilder::table('fee_invoices')->where('id', $invId)->first();
    $check('invoice status becomes partial', ($inv['status'] ?? '') === 'partial');

    // A refunded/failed payment must NOT count toward paid.
    QueryBuilder::table('fee_payments')->insert([
        'receipt_no' => 'TEST-RC-' . substr(md5($invNo . 'b'), 0, 8),
        'fee_invoice_id' => $invId, 'student_id' => $sid, 'amount' => 999,
        'payment_date' => date('Y-m-d'), 'method' => 'cash', 'status' => 'refunded',
        'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
    ]);
    $check('refunded payment does not change outstanding (still 350)', abs((FeeInvoice::outstanding($invId) ?? -1) - 350.0) < 0.001);

    // Paying the remainder settles it; outstanding never goes negative.
    QueryBuilder::table('fee_payments')->insert([
        'receipt_no' => 'TEST-RC-' . substr(md5($invNo . 'c'), 0, 8),
        'fee_invoice_id' => $invId, 'student_id' => $sid, 'amount' => 350,
        'payment_date' => date('Y-m-d'), 'method' => 'cash', 'status' => 'success',
        'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
    ]);
    FeeInvoice::recalculate($invId);
    $check('outstanding fully paid = 0 (never negative)', abs((FeeInvoice::outstanding($invId) ?? -1) - 0.0) < 0.001);

    $check('outstanding of a non-existent invoice is null', FeeInvoice::outstanding(999999999) === null);

    // Cleanup.
    QueryBuilder::table('fee_payments')->where('fee_invoice_id', $invId)->delete();
    QueryBuilder::table('fee_invoices')->where('id', $invId)->delete();
}

// --- Static guards: storePayment enforces ownership + no overpayment server-side.
$fc = (string) file_get_contents(dirname(__DIR__) . '/app/Controllers/Admin/FeeController.php');
$check('storePayment verifies invoice belongs to the student', str_contains($fc, "(int) \$invoice['student_id'] !== (int) \$data['student_id']"));
$check('storePayment rejects amount above outstanding', str_contains($fc, 'FeeInvoice::outstanding') && str_contains($fc, 'exceeds the outstanding balance'));
$check('payment write remains wrapped in a transaction', str_contains($fc, 'Connection::instance()->transaction'));

echo "PASS: {$pass}   FAIL: {$fail}\n";
exit($fail === 0 ? 0 : 1);
