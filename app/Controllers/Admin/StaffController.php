<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\Staff;
use App\Services\AuditLogger;
use Core\Http\Request;
use Core\Http\Response;
use Core\Routing\Controller;

/**
 * Manages all staff (primarily non-teaching) and departments.
 */
final class StaffController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = [
            'search'     => (string) $request->query('search', ''),
            'staff_type' => (string) $request->query('staff_type', ''),
            'status'     => (string) $request->query('status', ''),
        ];
        $page = max(1, (int) $request->query('page', 1));
        $result = Staff::paginate($page, (int) config('app.per_page', 15), Staff::filtered($filters));

        return $this->view('admin.staff.index', [
            'title'   => 'Staff',
            'staff'   => $result['data'],
            'meta'    => $result['meta'],
            'filters' => $filters,
        ]);
    }

    public function store(Request $request): Response
    {
        $data = $this->validate($request, [
            'first_name'  => 'required|string|max:80',
            'last_name'   => 'required|string|max:80',
            'staff_type'  => 'required|in:teaching,non_teaching',
            'designation' => 'nullable|string|max:120',
            'email'       => 'nullable|email|max:190',
            'phone'       => 'nullable|phone',
            'basic_salary'=> 'nullable|numeric',
        ]);

        $staffId = Staff::create([
            'employee_code' => Staff::generateEmployeeCode(),
            'first_name'  => $data['first_name'],
            'last_name'   => $data['last_name'],
            'staff_type'  => $data['staff_type'],
            'designation' => $data['designation'] ?: null,
            'email'       => $data['email'] ?: null,
            'phone'       => $data['phone'] ?: null,
            'basic_salary'=> $data['basic_salary'] ?: 0,
            'joining_date'=> date('Y-m-d'),
            'status'      => 'active',
        ]);

        AuditLogger::log('staff.created', 'staff', $staffId, 'Added staff ' . $data['first_name']);
        return $this->redirect('/admin/staff', ['success' => 'Staff member added.']);
    }

    public function update(Request $request, string $id): Response
    {
        if (Staff::find((int) $id) === null) {
            abort(404, 'Staff not found.');
        }
        $data = $this->validate($request, [
            'first_name'  => 'required|string|max:80',
            'last_name'   => 'required|string|max:80',
            'designation' => 'nullable|string|max:120',
            'status'      => 'required|in:active,inactive,on_leave,resigned',
        ]);
        Staff::updateById((int) $id, [
            'first_name'  => $data['first_name'],
            'last_name'   => $data['last_name'],
            'designation' => $data['designation'] ?: null,
            'phone'       => $request->input('phone'),
            'basic_salary'=> (float) $request->input('basic_salary', 0),
            'status'      => $data['status'],
        ]);
        AuditLogger::log('staff.updated', 'staff', (int) $id, 'Updated staff');
        return $this->redirect('/admin/staff', ['success' => 'Staff updated.']);
    }

    public function destroy(Request $request, string $id): Response
    {
        if (Staff::find((int) $id) === null) {
            abort(404, 'Staff not found.');
        }
        Staff::deleteById((int) $id);
        AuditLogger::log('staff.deleted', 'staff', (int) $id, 'Removed staff');
        return $this->redirect('/admin/staff', ['success' => 'Staff removed.']);
    }
}
