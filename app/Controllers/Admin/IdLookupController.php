<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Services\AuditLogger;
use App\Services\IdResolverService;
use Core\Http\Request;
use Core\Http\Response;
use Core\Routing\Controller;

/**
 * Universal "Go to Record by ID" admin tool. Resolves any unique ERP identifier
 * to its record and redirects there. Every lookup is audit-logged; the target
 * page still enforces its own permission checks, so this never bypasses RBAC.
 */
final class IdLookupController extends Controller
{
    public function index(Request $request): Response
    {
        $id = trim((string) $request->query('id', ''));
        $error = null;

        if ($id !== '') {
            $result = (new IdResolverService())->resolve($id);
            AuditLogger::log('record.lookup', 'lookup', null, "ID lookup '{$id}' => " . ($result['type'] ?? 'not found'));
            if ($result !== null) {
                return $this->redirect($result['route']);
            }
            $error = 'No record found for ID "' . $id . '". Check the ID and try again.';
        }

        return $this->view('admin.lookup.index', [
            'title' => 'Go to Record by ID',
            'query' => $id,
            'error' => $error,
        ]);
    }
}
