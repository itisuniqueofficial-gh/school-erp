<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\GovSyncLog;
use App\Services\GovernmentIntegrationService;
use Core\Http\Request;
use Core\Http\Response;
use Core\Routing\Controller;

/**
 * Admin: configure government integration adapters (UDISE+/APAAR/ABC/DigiLocker/
 * NAD/EMIS/Scholarship). Credentials are encrypted at rest and never rendered.
 */
final class IntegrationController extends Controller
{
    public function index(Request $request): Response
    {
        $svc = new GovernmentIntegrationService();
        $providers = [];
        foreach (array_keys(GovernmentIntegrationService::providers()) as $p) {
            $providers[$p] = [
                'meta'   => GovernmentIntegrationService::providers()[$p],
                'config' => $svc->publicConfig($p),
            ];
        }

        return $this->view('admin.settings.integrations', [
            'title'     => 'Government Integrations',
            'providers' => $providers,
            'logs'      => GovSyncLog::recent(25),
        ]);
    }

    public function save(Request $request, string $provider): Response
    {
        if (!GovernmentIntegrationService::isProvider($provider)) {
            abort(404, 'Unknown integration provider.');
        }
        $svc = new GovernmentIntegrationService();

        $input = ['enabled' => $request->boolean('enabled'), 'mode' => (string) $request->input('mode', 'sandbox')];
        foreach (GovernmentIntegrationService::providers()[$provider]['fields'] as $field) {
            $input[$field] = (string) $request->input($field, '');
        }

        $svc->save($provider, $input);
        return $this->redirect('/admin/settings/integrations', ['success' => ucfirst($provider) . ' integration saved.']);
    }
}
