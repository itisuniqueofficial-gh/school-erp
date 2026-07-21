<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Models\FeeInvoice;
use Core\Database\QueryBuilder;
use Core\Http\Request;
use Core\Http\Response;
use Core\Routing\Controller;

/**
 * REST API for fees.
 */
final class FeeApiController extends Controller
{
    public function index(Request $request): Response
    {
        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(100, max(1, (int) $request->query('per_page', 15)));
        $result = FeeInvoice::paginate($page, $perPage, QueryBuilder::table('fee_invoices')->orderBy('id', 'DESC'));
        return Response::json(['success' => true, 'data' => $result['data'], 'meta' => $result['meta']]);
    }

    public function forStudent(Request $request, string $id): Response
    {
        return $this->success(FeeInvoice::forStudent((int) $id));
    }
}
