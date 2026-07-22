<?php

declare(strict_types=1);

namespace App\Controllers;

use Core\Database\QueryBuilder;
use Core\Http\Request;
use Core\Http\Response;
use Core\Routing\Controller;

/**
 * Global search across students, teachers, classes, subjects and exams.
 * Results are categorized; each links to the relevant profile/page.
 * Restricted to staff roles at the route level.
 */
final class SearchController extends Controller
{
    /** Build categorized results; also exposed as JSON for live search. */
    private function results(string $q): array
    {
        $out = ['students' => [], 'teachers' => [], 'classes' => [], 'subjects' => [], 'exams' => []];
        if (mb_strlen($q) < 2) {
            return $out;
        }
        $like = '%' . $q . '%';

        try {
            $out['students'] = QueryBuilder::table('students')
                ->select(['id', 'public_id', 'admission_no', 'first_name', 'last_name', 'roll_no'])
                ->whereNull('deleted_at')
                ->whereRaw('(`first_name` LIKE ? OR `last_name` LIKE ? OR `admission_no` LIKE ? OR `public_id` LIKE ? OR `roll_no` LIKE ?)', [$like, $like, $like, $like, $like])
                ->limit(8)->get();

            $out['teachers'] = QueryBuilder::table('staff')
                ->select(['id', 'public_id', 'employee_code', 'first_name', 'last_name'])
                ->whereNull('deleted_at')->where('staff_type', 'teaching')
                ->whereRaw('(`first_name` LIKE ? OR `last_name` LIKE ? OR `employee_code` LIKE ? OR `public_id` LIKE ?)', [$like, $like, $like, $like])
                ->limit(8)->get();

            $out['classes'] = QueryBuilder::table('classes')
                ->select(['id', 'name', 'code'])
                ->whereRaw('(`name` LIKE ? OR `code` LIKE ?)', [$like, $like])->limit(8)->get();

            $out['subjects'] = QueryBuilder::table('subjects')
                ->select(['id', 'name', 'code'])
                ->whereRaw('(`name` LIKE ? OR `code` LIKE ?)', [$like, $like])->limit(8)->get();

            $out['exams'] = QueryBuilder::table('exams')
                ->select(['id', 'name'])->whereRaw('`name` LIKE ?', [$like])->limit(8)->get();
        } catch (\Throwable) {
        }
        return $out;
    }

    public function index(Request $request): Response
    {
        $q = trim((string) $request->query('q', ''));
        $results = $this->results($q);
        if ($request->expectsJson() || $request->query('format') === 'json') {
            return $this->json(['query' => $q, 'results' => $results]);
        }
        return $this->view('search.index', [
            'title'   => 'Search results',
            'q'       => $q,
            'results' => $results,
            'total'   => array_sum(array_map('count', $results)),
        ]);
    }
}
