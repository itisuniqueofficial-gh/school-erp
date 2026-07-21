<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Models\Staff;
use Core\Http\Request;
use Core\Http\Response;
use Core\Routing\Controller;

/**
 * REST API for teaching staff.
 */
final class TeacherApiController extends Controller
{
    public function index(Request $request): Response
    {
        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(100, max(1, (int) $request->query('per_page', 15)));
        $result = Staff::paginate($page, $perPage, Staff::filtered([
            'search'     => (string) $request->query('search', ''),
            'staff_type' => 'teaching',
        ]));
        return Response::json(['success' => true, 'data' => $result['data'], 'meta' => $result['meta']]);
    }

    public function show(Request $request, string $id): Response
    {
        $teacher = Staff::profile((int) $id);
        return $teacher === null ? $this->error('Teacher not found.', 404) : $this->success($teacher);
    }
}
