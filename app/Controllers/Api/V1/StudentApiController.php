<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Models\Student;
use Core\Http\Request;
use Core\Http\Response;
use Core\Routing\Controller;

/**
 * REST API for students (paginated, filterable).
 */
final class StudentApiController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = [
            'search'     => (string) $request->query('search', ''),
            'class_id'   => (int) $request->query('class_id', 0),
            'section_id' => (int) $request->query('section_id', 0),
            'status'     => (string) $request->query('status', ''),
        ];
        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(100, max(1, (int) $request->query('per_page', 15)));
        $result = Student::paginate($page, $perPage, Student::filtered($filters));

        return Response::json([
            'success' => true,
            'data'    => $result['data'],
            'meta'    => $result['meta'],
        ]);
    }

    public function show(Request $request, string $id): Response
    {
        $student = Student::profile((int) $id);
        return $student === null
            ? $this->error('Student not found.', 404)
            : $this->success($student);
    }

    public function store(Request $request): Response
    {
        $data = $this->validate($request, [
            'first_name' => 'required|string|max:80',
            'last_name'  => 'required|string|max:80',
            'gender'     => 'required|in:male,female,other',
            'class_id'   => 'nullable|integer',
        ]);
        $id = Student::create(array_merge($data, [
            'admission_no' => Student::generateAdmissionNo(),
            'admission_date' => date('Y-m-d'),
            'status' => 'active',
        ]));
        return $this->success(Student::find($id), 'Student created.', 201);
    }

    public function update(Request $request, string $id): Response
    {
        if (Student::find((int) $id) === null) {
            return $this->error('Student not found.', 404);
        }
        $data = $this->validate($request, [
            'first_name' => 'required|string|max:80',
            'last_name'  => 'required|string|max:80',
        ]);
        Student::updateById((int) $id, $data);
        return $this->success(Student::find((int) $id), 'Student updated.');
    }

    public function destroy(Request $request, string $id): Response
    {
        if (Student::find((int) $id) === null) {
            return $this->error('Student not found.', 404);
        }
        Student::deleteById((int) $id);
        return $this->success(null, 'Student deleted.');
    }
}
