<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Services\AuditLogger;
use Core\Database\QueryBuilder;
use Core\Http\Request;
use Core\Http\Response;
use Core\Routing\Controller;

/**
 * Transport: routes, vehicles (with driver + GPS-ready fields), and student allocation.
 */
final class TransportController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->view('admin.transport.index', [
            'title'       => 'Transport',
            'routes'      => QueryBuilder::table('transport_routes')->orderBy('name')->get(),
            'vehicles'    => QueryBuilder::table('vehicles')
                ->select(['vehicles.*', 'transport_routes.name AS route_name'])
                ->leftJoin('transport_routes', 'vehicles.route_id', '=', 'transport_routes.id')
                ->orderBy('vehicles.registration_no')->get(),
            'assignments' => QueryBuilder::table('transport_assignments')
                ->select(['transport_assignments.*', 'students.first_name', 'students.last_name', 'transport_routes.name AS route_name'])
                ->leftJoin('students', 'transport_assignments.student_id', '=', 'students.id')
                ->leftJoin('transport_routes', 'transport_assignments.route_id', '=', 'transport_routes.id')
                ->orderBy('transport_assignments.id', 'DESC')->limit(50)->get(),
        ]);
    }

    public function storeRoute(Request $request): Response
    {
        $data = $this->validate($request, [
            'name' => 'required|string|max:150|unique:transport_routes,name',
            'fare' => 'nullable|numeric',
        ]);
        $id = QueryBuilder::table('transport_routes')->insert([
            'name'        => $data['name'],
            'start_point' => $request->input('start_point'),
            'end_point'   => $request->input('end_point'),
            'fare'        => (float) $request->input('fare', 0),
            'created_at'  => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        AuditLogger::log('transport.route_added', 'route', $id, 'Added route ' . $data['name']);
        return $this->redirect('/admin/transport', ['success' => 'Route added.']);
    }

    public function storeVehicle(Request $request): Response
    {
        $data = $this->validate($request, [
            'registration_no' => 'required|string|max:40|unique:vehicles,registration_no',
            'capacity'        => 'nullable|integer',
        ]);
        $id = QueryBuilder::table('vehicles')->insert([
            'registration_no' => $data['registration_no'],
            'model'           => $request->input('model'),
            'capacity'        => (int) $request->input('capacity', 40),
            'driver_name'     => $request->input('driver_name'),
            'driver_phone'    => $request->input('driver_phone'),
            'route_id'        => (int) ($request->input('route_id') ?: 0) ?: null,
            'status'          => 'active',
            'created_at'      => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        AuditLogger::log('transport.vehicle_added', 'vehicle', $id, 'Added vehicle');
        return $this->redirect('/admin/transport', ['success' => 'Vehicle added.']);
    }

    public function assign(Request $request): Response
    {
        $data = $this->validate($request, [
            'student_id' => 'required|integer',
            'route_id'   => 'required|integer',
        ]);

        // One active assignment per student (unique student_id).
        $existing = QueryBuilder::table('transport_assignments')->where('student_id', (int) $data['student_id'])->first();
        $payload = [
            'route_id'     => (int) $data['route_id'],
            'vehicle_id'   => (int) ($request->input('vehicle_id') ?: 0) ?: null,
            'pickup_point' => $request->input('pickup_point'),
            'updated_at'   => date('Y-m-d H:i:s'),
        ];
        if ($existing) {
            QueryBuilder::table('transport_assignments')->where('id', $existing['id'])->update($payload);
        } else {
            $payload['student_id'] = (int) $data['student_id'];
            $payload['created_at'] = date('Y-m-d H:i:s');
            QueryBuilder::table('transport_assignments')->insert($payload);
        }

        AuditLogger::log('transport.assigned', 'transport', (int) $data['student_id'], 'Assigned transport');
        return $this->redirect('/admin/transport', ['success' => 'Student assigned to route.']);
    }
}
