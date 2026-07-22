<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\AuditLog;
use Core\Http\Request;
use Core\Http\Response;
use Core\Routing\Controller;

/**
 * Read-only viewer for the immutable audit trail. Supports filtering by action,
 * entity type, actor/description search, and date range, with pagination.
 */
final class AuditController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = [
            'action'      => (string) $request->query('action', ''),
            'entity_type' => (string) $request->query('entity_type', ''),
            'q'           => (string) $request->query('q', ''),
            'date_from'   => (string) $request->query('date_from', ''),
            'date_to'     => (string) $request->query('date_to', ''),
        ];
        $page = max(1, (int) $request->query('page', 1));

        $result = AuditLog::paginate($page, (int) config('app.per_page', 20), AuditLog::filtered($filters));

        return $this->view('admin.audit.index', [
            'title'    => 'Audit Log',
            'logs'     => $result['data'],
            'meta'     => $result['meta'],
            'filters'  => $filters,
            'actions'  => AuditLog::distinctActions(),
            'entities' => AuditLog::distinctEntities(),
        ]);
    }
}
