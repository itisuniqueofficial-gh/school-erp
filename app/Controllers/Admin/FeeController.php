<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\ClassModel;
use App\Models\FeeInvoice;
use App\Models\FeePayment;
use App\Models\FeeStructure;
use App\Services\AuditLogger;
use Core\Database\Connection;
use Core\Database\QueryBuilder;
use Core\Http\Request;
use Core\Http\Response;
use Core\Routing\Controller;

/**
 * Fees: structures, invoicing, collection and receipts.
 */
final class FeeController extends Controller
{
    public function index(Request $request): Response
    {
        $collectedToday = (float) ($this->safeSum(fn () => QueryBuilder::table('fee_payments')
            ->where('payment_date', date('Y-m-d'))->where('status', 'success')));
        $collectedMonth = (float) ($this->safeSum(fn () => QueryBuilder::table('fee_payments')
            ->where('status', 'success')->whereLike('payment_date', date('Y-m') . '%')));

        $outstanding = 0.0;
        $recent = [];
        try {
            $outstanding = (float) (QueryBuilder::table('fee_invoices')
                ->whereIn('status', ['unpaid', 'partial', 'overdue'])
                ->selectRaw('COALESCE(SUM(total_amount + fine_amount - discount_amount - paid_amount),0) AS t')
                ->value('t') ?? 0);
            $recent = QueryBuilder::table('fee_payments')
                ->select(['fee_payments.*', 'students.first_name', 'students.last_name'])
                ->leftJoin('students', 'fee_payments.student_id', '=', 'students.id')
                ->orderBy('fee_payments.id', 'DESC')->limit(10)->get();
        } catch (\Throwable) {
        }

        return $this->view('admin.fees.index', [
            'title'          => 'Fees',
            'collectedToday' => $collectedToday,
            'collectedMonth' => $collectedMonth,
            'outstanding'    => $outstanding,
            'recent'         => $recent,
        ]);
    }

    public function structures(Request $request): Response
    {
        return $this->view('admin.fees.structures', [
            'title'      => 'Fee Structures',
            'structures' => FeeStructure::withClass(),
            'classes'    => ClassModel::ordered(),
            'categories' => QueryBuilder::table('fee_categories')->orderBy('name')->get(),
        ]);
    }

    public function storeStructure(Request $request): Response
    {
        $data = $this->validate($request, [
            'name'      => 'required|string|max:150',
            'class_id'  => 'nullable|integer',
            'frequency' => 'required|in:monthly,quarterly,half_yearly,yearly,one_time',
        ]);

        $id = FeeStructure::create([
            'name'      => $data['name'],
            'class_id'  => (int) ($data['class_id'] ?? 0) ?: null,
            'frequency' => $data['frequency'],
        ]);

        AuditLogger::log('fee.structure_created', 'fee_structure', $id, 'Created structure ' . $data['name']);
        return $this->redirect('/admin/fees/structures', ['success' => 'Fee structure created.']);
    }

    /**
     * Fee collection screen; search a student and show their invoices.
     */
    public function collect(Request $request): Response
    {
        $search = (string) $request->query('search', '');
        $student = null;
        $invoices = [];

        if ($search !== '') {
            $student = QueryBuilder::table('students')
                ->whereRaw('(`admission_no` = ? OR `first_name` LIKE ? OR `last_name` LIKE ?)', [$search, "%{$search}%", "%{$search}%"])
                ->whereNull('deleted_at')->first();
            if ($student !== null) {
                $invoices = FeeInvoice::forStudent((int) $student['id']);
            }
        }

        return $this->view('admin.fees.collect', [
            'title'    => 'Collect Fees',
            'search'   => $search,
            'student'  => $student,
            'invoices' => $invoices,
        ]);
    }

    public function storePayment(Request $request): Response
    {
        $data = $this->validate($request, [
            'fee_invoice_id' => 'required|integer',
            'student_id'     => 'required|integer',
            'amount'         => 'required|numeric|min:0.01',
            'method'         => 'required|in:cash,card,bank_transfer,cheque,online,upi',
        ]);

        try {
            $paymentId = Connection::instance()->transaction(function () use ($data, $request): int {
                $pid = FeePayment::create([
                    'receipt_no'     => FeePayment::generateReceiptNo(),
                    'fee_invoice_id' => (int) $data['fee_invoice_id'],
                    'student_id'     => (int) $data['student_id'],
                    'amount'         => (float) $data['amount'],
                    'payment_date'   => date('Y-m-d'),
                    'method'         => $data['method'],
                    'reference'      => $request->input('reference'),
                    'status'         => 'success',
                    'collected_by'   => auth()->id(),
                ]);
                FeeInvoice::recalculate((int) $data['fee_invoice_id']);
                return $pid;
            });
        } catch (\Throwable $e) {
            logger()->error('Payment failed: ' . $e->getMessage());
            return $this->back(['payment' => ['Could not record the payment.']]);
        }

        AuditLogger::log('fee.payment', 'fee_payment', $paymentId, 'Collected fee payment');
        return $this->redirect('/admin/fees/receipt/' . $paymentId, ['success' => 'Payment recorded.']);
    }

    public function receipt(Request $request, string $id): Response
    {
        $receipt = FeePayment::receipt((int) $id);
        if ($receipt === null) {
            abort(404, 'Receipt not found.');
        }
        return $this->view('admin.fees.receipt', [
            'title'   => 'Receipt ' . $receipt['receipt_no'],
            'receipt' => $receipt,
        ]);
    }

    private function safeSum(callable $q): float
    {
        try {
            return (float) ($q()->selectRaw('COALESCE(SUM(amount),0) AS t')->value('t') ?? 0);
        } catch (\Throwable) {
            return 0.0;
        }
    }
}
