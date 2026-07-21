<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Services\AuditLogger;
use Core\Database\QueryBuilder;
use Core\Http\Request;
use Core\Http\Response;
use Core\Routing\Controller;

/**
 * HR & Payroll: staff leaves, payroll (payslips) and holidays.
 */
final class HrController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->view('admin.hr.index', [
            'title'    => 'HR & Payroll',
            'leaves'   => QueryBuilder::table('leaves')
                ->select(['leaves.*', 'staff.first_name', 'staff.last_name', 'leave_types.name AS leave_type'])
                ->leftJoin('staff', 'leaves.staff_id', '=', 'staff.id')
                ->leftJoin('leave_types', 'leaves.leave_type_id', '=', 'leave_types.id')
                ->orderBy('leaves.id', 'DESC')->limit(50)->get(),
            'payrolls' => QueryBuilder::table('payrolls')
                ->select(['payrolls.*', 'staff.first_name', 'staff.last_name'])
                ->leftJoin('staff', 'payrolls.staff_id', '=', 'staff.id')
                ->orderBy('payrolls.id', 'DESC')->limit(50)->get(),
            'holidays'   => QueryBuilder::table('holidays')->orderBy('date', 'DESC')->limit(30)->get(),
            'leaveTypes' => QueryBuilder::table('leave_types')->orderBy('name')->get(),
        ]);
    }

    public function storeLeave(Request $request): Response
    {
        $data = $this->validate($request, [
            'staff_id'   => 'required|integer',
            'start_date' => 'required|date',
            'end_date'   => 'required|date',
        ]);
        $days = max(1, (int) ((strtotime($data['end_date']) - strtotime($data['start_date'])) / 86400) + 1);
        $id = QueryBuilder::table('leaves')->insert([
            'staff_id'      => (int) $data['staff_id'],
            'leave_type_id' => (int) ($request->input('leave_type_id') ?: 0) ?: null,
            'start_date'    => $data['start_date'],
            'end_date'      => $data['end_date'],
            'days'          => $days,
            'reason'        => $request->input('reason'),
            'status'        => 'pending',
            'created_at'    => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        AuditLogger::log('hr.leave_applied', 'leave', $id, 'Leave application');
        return $this->redirect('/admin/hr', ['success' => 'Leave application recorded.']);
    }

    public function updateLeaveStatus(Request $request, string $id): Response
    {
        $status = (string) $request->input('status', 'pending');
        if (!in_array($status, ['approved', 'rejected', 'cancelled', 'pending'], true)) {
            return $this->back(['status' => ['Invalid status.']]);
        }
        QueryBuilder::table('leaves')->where('id', (int) $id)->update([
            'status'      => $status,
            'approved_by' => auth()->id(),
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);
        AuditLogger::log('hr.leave_status', 'leave', (int) $id, 'Leave ' . $status);
        return $this->redirect('/admin/hr', ['success' => 'Leave ' . $status . '.']);
    }

    public function generatePayroll(Request $request): Response
    {
        $data = $this->validate($request, [
            'staff_id'     => 'required|integer',
            'month'        => 'required|integer|between:1,12',
            'year'         => 'required|integer',
            'basic_salary' => 'required|numeric',
        ]);

        $basic = (float) $data['basic_salary'];
        $allow = (float) $request->input('allowances', 0);
        $deduct = (float) $request->input('deductions', 0);
        $net = $basic + $allow - $deduct;

        $existing = QueryBuilder::table('payrolls')
            ->where('staff_id', (int) $data['staff_id'])
            ->where('month', (int) $data['month'])
            ->where('year', (int) $data['year'])->first();

        $payload = [
            'basic_salary' => $basic, 'allowances' => $allow, 'deductions' => $deduct,
            'net_salary' => $net, 'status' => 'paid', 'paid_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        if ($existing) {
            QueryBuilder::table('payrolls')->where('id', $existing['id'])->update($payload);
        } else {
            $payload = array_merge($payload, [
                'staff_id' => (int) $data['staff_id'], 'month' => (int) $data['month'],
                'year' => (int) $data['year'], 'created_at' => date('Y-m-d H:i:s'),
            ]);
            QueryBuilder::table('payrolls')->insert($payload);
        }

        AuditLogger::log('hr.payroll', 'payroll', (int) $data['staff_id'], 'Generated payslip');
        return $this->redirect('/admin/hr', ['success' => 'Payslip generated (net ' . money($net) . ').']);
    }

    public function storeHoliday(Request $request): Response
    {
        $data = $this->validate($request, [
            'name' => 'required|string|max:150',
            'date' => 'required|date',
        ]);
        $id = QueryBuilder::table('holidays')->insert([
            'name'        => $data['name'],
            'date'        => $data['date'],
            'description' => $request->input('description'),
            'created_at'  => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        AuditLogger::log('hr.holiday_added', 'holiday', $id, 'Added holiday ' . $data['name']);
        return $this->redirect('/admin/hr', ['success' => 'Holiday added.']);
    }
}
