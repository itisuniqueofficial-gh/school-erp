<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PaymentTransaction;
use Core\Database\QueryBuilder;
use Core\Security\Crypto;

/**
 * Modular payment-gateway configuration + server-side verification.
 *
 * Security model:
 *  - Gateway credentials live in the `settings` table (group = "payment"), and
 *    every SECRET field is encrypted at rest with AES-256-GCM (Core\Security\Crypto).
 *  - Secrets are NEVER returned to any view/API/JS/URL: `publicConfig()` masks
 *    them and `enabled()` exposes only non-sensitive display data.
 *  - Payment success is only ever established server-side: `verifyRazorpaySignature()`
 *    and `verifyWebhookSignature()` recompute HMACs from the stored secret using a
 *    constant-time comparison. A frontend "success" alone changes nothing.
 */
final class PaymentGatewayService
{
    /**
     * Supported gateways and their configuration fields. Fields listed in
     * `secret` are encrypted at rest and never exposed in clear text.
     *
     * @var array<string,array{label:string,fields:array<int,string>,secret:array<int,string>,sig:?string}>
     */
    private const GATEWAYS = [
        'razorpay' => [
            'label'  => 'Razorpay',
            'fields' => ['key_id', 'key_secret', 'webhook_secret'],
            'secret' => ['key_secret', 'webhook_secret'],
            'required' => ['key_id', 'key_secret'],
            'sig'    => 'key_secret',
        ],
        'phonepe' => [
            'label'  => 'PhonePe',
            'fields' => ['merchant_id', 'salt_key', 'salt_index', 'webhook_secret'],
            'secret' => ['salt_key', 'webhook_secret'],
            'required' => ['merchant_id', 'salt_key', 'salt_index'],
            'sig'    => 'salt_key',
        ],
        'mobikwik' => [
            'label'  => 'MobiKwik',
            'fields' => ['merchant_id', 'secret_key', 'webhook_secret'],
            'secret' => ['secret_key', 'webhook_secret'],
            'required' => ['merchant_id', 'secret_key'],
            'sig'    => 'secret_key',
        ],
    ];

    /** @return array<string,array{label:string,fields:array<int,string>,secret:array<int,string>,sig:?string}> */
    public static function supported(): array
    {
        return self::GATEWAYS;
    }

    public static function isSupported(string $gateway): bool
    {
        return isset(self::GATEWAYS[$gateway]);
    }

    /**
     * Full decrypted config for a gateway (INTERNAL use only — contains secrets).
     *
     * @return array<string,mixed>
     */
    public function get(string $gateway): array
    {
        if (!self::isSupported($gateway)) {
            return [];
        }
        $row = QueryBuilder::table('settings')->where('key', 'payment.' . $gateway)->first();
        $decoded = $row ? json_decode((string) $row['value'], true) : [];

        return $this->normalize($gateway, is_array($decoded) ? $decoded : []);
    }

    /**
     * Produce a complete, valid configuration structure for a gateway from a
     * (possibly empty or partial) raw settings array. This is the single place
     * that shapes gateway config, so no caller ever touches missing keys:
     *   - enabled: bool
     *   - mode: 'test' | 'live' (invalid/absent values normalize to 'test')
     *   - every declared field: string (secrets decrypted)
     *
     * @param array<string,mixed> $raw
     * @return array<string,mixed>
     */
    private function normalize(string $gateway, array $raw): array
    {
        $meta = self::GATEWAYS[$gateway];

        $mode = $raw['mode'] ?? 'test';
        if (!in_array($mode, ['test', 'live'], true)) {
            $mode = 'test';
        }

        $config = [
            'gateway' => $gateway,
            'label'   => $meta['label'],
            'enabled' => (bool) ($raw['enabled'] ?? false),
            'mode'    => $mode,
        ];
        foreach ($meta['fields'] as $field) {
            $value = (string) ($raw[$field] ?? '');
            if ($value !== '' && in_array($field, $meta['secret'], true)) {
                $value = (string) (Crypto::decrypt($value) ?? '');
            }
            $config[$field] = $value;
        }
        return $config;
    }

    /**
     * Persist a gateway config, encrypting secret fields at rest. A gateway can
     * only be enabled when all its required credentials are present (checked
     * against both submitted and previously-saved values).
     *
     * @param array<string,mixed> $input
     * @return array{ok:bool,message:string}
     */
    public function save(string $gateway, array $input): array
    {
        if (!self::isSupported($gateway)) {
            return ['ok' => false, 'message' => 'Unknown payment gateway.'];
        }
        $meta = self::GATEWAYS[$gateway];
        $wantEnabled = !empty($input['enabled']);
        $store = [
            'enabled' => $wantEnabled,
            'mode'    => (($input['mode'] ?? 'test') === 'live') ? 'live' : 'test',
        ];
        $existing = $this->get($gateway); // decrypted current values
        $plainValues = [];
        foreach ($meta['fields'] as $field) {
            $new = trim((string) ($input[$field] ?? ''));
            // Keep the existing secret if the field was left blank (masked in UI).
            $plain = ($new === '' && in_array($field, $meta['secret'], true))
                ? (string) ($existing[$field] ?? '')
                : $new;
            $plainValues[$field] = $plain;
            $store[$field] = (in_array($field, $meta['secret'], true) && $plain !== '')
                ? (string) Crypto::encrypt($plain)
                : $plain;
        }

        // Prevent enabling with incomplete credentials: save the entered values
        // but keep the gateway disabled and report which fields are missing.
        $missing = [];
        foreach (($meta['required'] ?? []) as $req) {
            if (($plainValues[$req] ?? '') === '') {
                $missing[] = $req;
            }
        }
        $message = ucfirst($gateway) . ' settings saved.';
        $ok = true;
        if ($wantEnabled && $missing !== []) {
            $store['enabled'] = false;
            $ok = false;
            $message = 'Saved, but ' . $meta['label'] . ' was NOT enabled — missing: '
                . implode(', ', array_map(static fn ($f) => str_replace('_', ' ', $f), $missing)) . '.';
        }

        $payload = json_encode($store, JSON_UNESCAPED_SLASHES);
        $exists = QueryBuilder::table('settings')->where('key', 'payment.' . $gateway)->first();
        if ($exists) {
            QueryBuilder::table('settings')->where('key', 'payment.' . $gateway)
                ->update(['value' => $payload, 'updated_at' => date('Y-m-d H:i:s')]);
        } else {
            QueryBuilder::table('settings')->insert([
                'group' => 'payment', 'key' => 'payment.' . $gateway, 'value' => $payload,
                'type' => 'json', 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }

        return ['ok' => $ok, 'message' => $message];
    }

    public function isEnabled(string $gateway): bool
    {
        return (bool) ($this->get($gateway)['enabled'] ?? false);
    }

    /**
     * Config safe to render in the admin UI: secrets are masked, never clear.
     *
     * @return array<string,mixed>
     */
    public function publicConfig(string $gateway): array
    {
        $config = $this->get($gateway);
        if ($config === []) {
            return [];
        }
        foreach (self::GATEWAYS[$gateway]['secret'] as $field) {
            $val = (string) ($config[$field] ?? '');
            // Report ONLY whether the secret is set. Never return any portion of
            // the plaintext (a fixed placeholder is used, not the real value).
            $config[$field] = $val === '' ? '' : '••••••••';
            $config[$field . '_set'] = $val !== '';
        }
        return $config;
    }

    /**
     * Enabled gateways for the checkout UI. Contains ONLY display data — no
     * credentials whatsoever. A disabled gateway never appears here.
     *
     * @return array<int,array{gateway:string,label:string,mode:string}>
     */
    public function enabled(): array
    {
        $out = [];
        foreach (array_keys(self::GATEWAYS) as $gw) {
            $c = $this->get($gw);
            if (!empty($c['enabled'])) {
                $out[] = ['gateway' => $gw, 'label' => (string) $c['label'], 'mode' => (string) $c['mode']];
            }
        }
        return $out;
    }

    // -- Server-side verification ------------------------------------------

    /**
     * Verify a Razorpay payment signature exactly as Razorpay documents:
     * HMAC_SHA256(order_id + "|" + payment_id, key_secret), constant-time compared.
     */
    public function verifyRazorpaySignature(string $orderId, string $paymentId, string $signature): bool
    {
        $secret = (string) ($this->get('razorpay')['key_secret'] ?? '');
        if ($secret === '' || $signature === '') {
            return false;
        }
        $expected = hash_hmac('sha256', $orderId . '|' . $paymentId, $secret);
        return hash_equals($expected, $signature);
    }

    /**
     * Verify a signed webhook payload for any gateway using its stored secret,
     * with a constant-time comparison. `$algo` defaults to sha256.
     */
    public function verifyWebhookSignature(string $gateway, string $rawPayload, string $signature, string $algo = 'sha256'): bool
    {
        if (!self::isSupported($gateway) || $signature === '') {
            return false;
        }
        $secret = (string) ($this->get($gateway)['webhook_secret'] ?? '');
        if ($secret === '') {
            return false;
        }
        $expected = hash_hmac($algo, $rawPayload, $secret);
        return hash_equals($expected, $signature);
    }

    // -- Ledger ------------------------------------------------------------

    /**
     * Record (or fetch) a pending transaction for a gateway order. Idempotent:
     * the DB UNIQUE(gateway, gateway_order_id) guarantees a single ledger row.
     *
     * @return int transaction id
     */
    public function recordOrder(string $gateway, string $orderId, float $amount, ?int $studentId, ?int $invoiceId, string $mode = 'test'): int
    {
        $existing = QueryBuilder::table('payment_transactions')
            ->where('gateway', $gateway)->where('gateway_order_id', $orderId)->first();
        if ($existing) {
            return (int) $existing['id'];
        }
        return PaymentTransaction::create([
            'uid'              => (new IdSequenceService())->generate('PAY'),
            'student_id'       => $studentId,
            'fee_invoice_id'   => $invoiceId,
            'gateway'          => $gateway,
            'gateway_order_id' => $orderId,
            'amount'           => $amount,
            'currency'         => 'INR',
            'status'           => 'pending',
            'mode'             => $mode === 'live' ? 'live' : 'test',
        ]);
    }

    /**
     * Settle a transaction after server-side verification. Duplicate settlement
     * is prevented: an already-successful row is left untouched.
     *
     * @return array{ok:bool,message:string}
     */
    public function markVerified(int $txnId, string $paymentId, string $status = 'successful', ?string $method = null): array
    {
        $txn = PaymentTransaction::find($txnId);
        if ($txn === null) {
            return ['ok' => false, 'message' => 'Transaction not found.'];
        }
        if (($txn['status'] ?? '') === 'successful') {
            return ['ok' => true, 'message' => 'Already settled (idempotent no-op).'];
        }
        PaymentTransaction::updateById($txnId, [
            'gateway_payment_id' => $paymentId,
            'status'             => $status,
            'method'             => $method,
            'signature_verified' => 1,
        ]);
        AuditLogger::log('payment.' . $status, 'payment_transaction', $txnId, 'Payment ' . $status . ' (' . ($txn['gateway'] ?? '') . ')');
        return ['ok' => true, 'message' => 'Transaction updated.'];
    }
}
