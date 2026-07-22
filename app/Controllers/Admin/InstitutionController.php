<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Services\InstitutionService;
use App\Services\AuditLogger;
use Core\Http\Request;
use Core\Http\Response;
use Core\Routing\Controller;

/**
 * Institution profile + modular ERP configuration: enable/disable optional
 * modules, set institution type/board, and customise terminology. Lets one
 * codebase operate as a government school, private school, or coaching institute.
 */
final class InstitutionController extends Controller
{
    private const TYPES = [
        'government' => 'Government / Public', 'government_aided' => 'Government-Aided',
        'private_aided' => 'Private Aided', 'private_unaided' => 'Private Unaided',
        'private' => 'Private', 'coaching' => 'Coaching Institute',
        'training' => 'Training Institute', 'other' => 'Other',
    ];
    private const BOARDS = [
        'cbse' => 'CBSE', 'cisce' => 'CISCE / ICSE', 'state' => 'State Board',
        'ib' => 'IB', 'cambridge' => 'Cambridge', 'nios' => 'NIOS',
        'other' => 'Other', 'na' => 'Not Applicable',
    ];

    public function index(Request $request): Response
    {
        $svc = new InstitutionService();
        return $this->view('admin.settings.institution', [
            'title'       => 'ERP Configuration',
            'modules'     => $svc->modules(),
            'moduleLabels' => InstitutionService::availableModules(),
            'terms'       => InstitutionService::termDefaults(),
            'svc'         => $svc,
            'types'       => self::TYPES,
            'boards'      => self::BOARDS,
            'profile'     => [
                'name'  => $svc->profile('name', (string) config('app.name', 'School ERP')),
                'type'  => $svc->profile('type', 'private'),
                'board' => $svc->profile('board', 'cbse'),
                'code'  => $svc->profile('code', ''),
                'short_name'    => $svc->profile('short_name', ''),
                'footer_text'   => $svc->profile('footer_text', ''),
                'copyright'     => $svc->profile('copyright', ''),
                'support_email' => $svc->profile('support_email', ''),
                'support_phone' => $svc->profile('support_phone', ''),
                'login_welcome' => $svc->profile('login_welcome', ''),
                'login_desc'    => $svc->profile('login_desc', ''),
            ],
        ]);
    }

    public function save(Request $request): Response
    {
        $svc = new InstitutionService();

        // Institution profile + branding.
        foreach (['name', 'type', 'board', 'code', 'short_name', 'footer_text', 'copyright', 'support_email', 'support_phone', 'login_welcome', 'login_desc'] as $key) {
            $svc->setProfile($key, (string) $request->input('profile_' . $key, ''));
        }

        // Module enable/disable (checkbox present => enabled).
        $submitted = (array) $request->input('modules', []);
        foreach (array_keys(InstitutionService::availableModules()) as $key) {
            $svc->setModule($key, isset($submitted[$key]));
        }

        // Terminology overrides.
        $terms = (array) $request->input('terms', []);
        foreach (array_keys(InstitutionService::termDefaults()) as $key) {
            $svc->setTerm($key, (string) ($terms[$key] ?? ''));
        }

        AuditLogger::log('institution.configured', 'settings', null, 'Updated ERP configuration');
        return $this->redirect('/admin/settings/institution', ['success' => 'ERP configuration saved.']);
    }
}
