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
 * to its record and redirects there. It cleanly distinguishes:
 *   - found + permitted        -> redirect to the record
 *   - found + not permitted    -> "Access denied" (never a false "not found")
 *   - found + archived/revoked -> shown with an archived notice + direct link
 *   - truly absent             -> "No record found"
 * Every lookup is audit-logged.
 */
final class IdLookupController extends Controller
{
    public function index(Request $request): Response
    {
        $raw = trim((string) $request->query('id', ''));
        $data = ['title' => 'Go to Record by ID', 'query' => $raw, 'error' => null, 'denied' => null, 'archived' => null];

        if ($raw === '') {
            return $this->view('admin.lookup.index', $data);
        }

        $result = (new IdResolverService())->resolve($raw);

        if ($result === null) {
            AuditLogger::log('record.lookup', 'lookup', null, "ID lookup '{$raw}' => not found");
            $data['error'] = 'No record found for ID "' . $raw . '". Check the ID and try again.';
            return $this->view('admin.lookup.index', $data);
        }

        if (!auth()->can($result['permission'])) {
            AuditLogger::log('record.lookup', 'lookup', null, "ID lookup '{$raw}' => access denied ({$result['type']} {$result['uid']})");
            $data['denied'] = 'This ' . strtolower($result['type']) . ' (' . $result['uid'] . ') exists, but you do not have permission to view it.';
            return $this->view('admin.lookup.index', $data);
        }

        if (!empty($result['archived'])) {
            AuditLogger::log('record.lookup', 'lookup', null, "ID lookup '{$raw}' => archived ({$result['type']} {$result['uid']})");
            $data['archived'] = [
                'type'  => $result['type'],
                'uid'   => $result['uid'],
                'name'  => $result['name'],
                'route' => $result['route'],
            ];
            return $this->view('admin.lookup.index', $data);
        }

        AuditLogger::log('record.lookup', 'lookup', null, "ID lookup '{$raw}' => found ({$result['type']} {$result['uid']})");
        return $this->redirect($result['route']);
    }
}
