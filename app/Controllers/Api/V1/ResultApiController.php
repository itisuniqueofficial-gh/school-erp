<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1;

use Core\Database\QueryBuilder;
use Core\Http\Request;
use Core\Http\Response;
use Core\Routing\Controller;

/**
 * REST API for examination results / report cards.
 */
final class ResultApiController extends Controller
{
    public function forStudent(Request $request, string $id): Response
    {
        $results = QueryBuilder::table('report_cards')
            ->select(['report_cards.*', 'exams.name AS exam_name'])
            ->leftJoin('exams', 'report_cards.exam_id', '=', 'exams.id')
            ->where('report_cards.student_id', (int) $id)
            ->orderBy('report_cards.id', 'DESC')
            ->get();
        return $this->success($results);
    }
}
