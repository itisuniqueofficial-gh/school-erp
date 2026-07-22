<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\PaymentTransaction;
use App\Services\PaymentGatewayService;
use App\Services\AuditLogger;
use Core\Http\Request;
use Core\Http\Response;
use Core\Routing\Controller;

/**
 * Admin: configure payment gateways (credentials encrypted at rest, never
 * rendered in clear text) and review the payment transactions ledger.
 */
final class PaymentSettingsController extends Controller
{
    public function index(Request $request): Response
    {
        $svc = new PaymentGatewayService();
        $gateways = [];
        foreach (array_keys(PaymentGatewayService::supported()) as $gw) {
            $gateways[$gw] = [
                'meta'   => PaymentGatewayService::supported()[$gw],
                'config' => $svc->publicConfig($gw), // secrets masked
            ];
        }

        return $this->view('admin.settings.payments', [
            'title'        => 'Payment Gateways',
            'gateways'     => $gateways,
            'transactions' => PaymentTransaction::recent(25),
        ]);
    }

    public function save(Request $request, string $gateway): Response
    {
        if (!PaymentGatewayService::isSupported($gateway)) {
            abort(404, 'Unknown payment gateway.');
        }
        $svc = new PaymentGatewayService();

        // Collect only this gateway's declared fields from the request.
        $input = ['enabled' => $request->boolean('enabled'), 'mode' => (string) $request->input('mode', 'test')];
        foreach (PaymentGatewayService::supported()[$gateway]['fields'] as $field) {
            $input[$field] = (string) $request->input($field, '');
        }

        $res = $svc->save($gateway, $input);
        AuditLogger::log('payment.gateway_configured', 'settings', null, 'Configured ' . $gateway . ' gateway (' . ($input['enabled'] ? 'enable requested' : 'disabled') . ')');
        return $this->redirect('/admin/settings/payments', [$res['ok'] ? 'success' : 'error' => $res['message']]);
    }
}
