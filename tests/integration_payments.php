<?php

declare(strict_types=1);

/**
 * Payment-gateway foundation test: encrypted-at-rest config, no-secret checkout
 * listing, server-side signature/webhook verification, and ledger idempotency.
 *
 *   php bin/console migrate:fresh && php bin/console seed
 *   php tests/integration_payments.php
 */

use App\Services\PaymentGatewayService;
use Core\Database\QueryBuilder;

$app = require dirname(__DIR__) . '/bootstrap/app.php';

$pass = 0; $fail = 0; $failures = [];
function check(string $l, bool $c): void { global $pass,$fail,$failures; if($c){$pass++;echo "  \033[32mPASS\033[0m  {$l}\n";}else{$fail++;$failures[]=$l;echo "  \033[31mFAIL\033[0m  {$l}\n";} }
function section(string $t): void { echo "\n\033[36m== {$t} ==\033[0m\n"; }

$svc = new PaymentGatewayService();
$SECRET = 'rzp_secret_' . bin2hex(random_bytes(6));
$WEBHOOK = 'whsec_' . bin2hex(random_bytes(6));

// ---------------------------------------------------------------------------
section('No PHP warnings on UNCONFIGURED gateways (regression: Undefined key "mode")');
// Promote warnings/notices to exceptions for this section so any undefined
// array key access fails loudly instead of printing a warning.
set_error_handler(static function (int $no, string $str): bool {
    throw new \ErrorException($str, 0, $no);
});
try {
    // mobikwik & phonepe have no settings row yet -> raw config is empty.
    $mk = $svc->get('mobikwik');
    check('get() on unconfigured gateway returns a valid mode', ($mk['mode'] ?? null) === 'test');
    check('get() includes enabled=false by default', ($mk['enabled'] ?? null) === false);
    $pub = $svc->publicConfig('phonepe');
    check('publicConfig() on unconfigured gateway has a mode', ($pub['mode'] ?? null) === 'test');
    $en = $svc->enabled();
    check('enabled() runs warning-free (none configured yet)', is_array($en));
    check('No warning thrown for any supported gateway', (function () use ($svc) {
        foreach (array_keys(PaymentGatewayService::supported()) as $gw) { $svc->get($gw); $svc->publicConfig($gw); }
        return true;
    })());
} catch (\ErrorException $ex) {
    check('NO PHP warning on unconfigured gateway config (got: ' . $ex->getMessage() . ')', false);
} finally {
    restore_error_handler();
}

// ---------------------------------------------------------------------------
section('Config is encrypted at rest and round-trips');
$svc->save('razorpay', [
    'enabled' => true, 'mode' => 'test',
    'key_id' => 'rzp_test_ABC123', 'key_secret' => $SECRET, 'webhook_secret' => $WEBHOOK,
]);
$cfg = $svc->get('razorpay');
check('Decrypted key_secret matches what was saved', $cfg['key_secret'] === $SECRET);
check('Decrypted webhook_secret matches', $cfg['webhook_secret'] === $WEBHOOK);
$rawStored = (string) QueryBuilder::table('settings')->where('key', 'payment.razorpay')->value('value');
check('Stored value does NOT contain the plaintext secret', strpos($rawStored, $SECRET) === false);
check('Stored value does NOT contain the plaintext webhook secret', strpos($rawStored, $WEBHOOK) === false);

// ---------------------------------------------------------------------------
section('publicConfig masks secrets (never leaks plaintext)');
$pub = $svc->publicConfig('razorpay');
check('key_secret is masked, not real', $pub['key_secret'] !== $SECRET && $pub['key_secret'] !== '');
check('key_secret_set flag is true', ($pub['key_secret_set'] ?? false) === true);
check('publicConfig contains NO plaintext secret anywhere', strpos(json_encode($pub), $SECRET) === false && strpos(json_encode($pub), $WEBHOOK) === false);
check('Non-secret key_id is visible', ($pub['key_id'] ?? '') === 'rzp_test_ABC123');

// ---------------------------------------------------------------------------
section('Blank secret on save keeps the existing value');
$svc->save('razorpay', ['enabled' => true, 'mode' => 'test', 'key_id' => 'rzp_test_XYZ', 'key_secret' => '', 'webhook_secret' => '']);
$cfg2 = $svc->get('razorpay');
check('key_id updated', $cfg2['key_id'] === 'rzp_test_XYZ');
check('key_secret preserved when submitted blank', $cfg2['key_secret'] === $SECRET);

// ---------------------------------------------------------------------------
section('Enabled-only checkout listing exposes no secrets');
$svc->save('phonepe', ['enabled' => false, 'mode' => 'test', 'merchant_id' => 'M1', 'salt_key' => 's', 'salt_index' => '1', 'webhook_secret' => 'w']);
$enabled = $svc->enabled();
$names = array_column($enabled, 'gateway');
check('Enabled gateway (razorpay) is listed', in_array('razorpay', $names, true));
check('Disabled gateway (phonepe) is NOT listed', !in_array('phonepe', $names, true));
check('Checkout list carries no secret fields', strpos(json_encode($enabled), $SECRET) === false && !isset($enabled[0]['key_secret']));

// ---------------------------------------------------------------------------
section('Server-side signature verification (Razorpay HMAC)');
$order = 'order_ABC'; $payment = 'pay_XYZ';
$validSig = hash_hmac('sha256', $order . '|' . $payment, $SECRET);
check('Valid signature verifies true', $svc->verifyRazorpaySignature($order, $payment, $validSig) === true);
check('Tampered signature verifies false', $svc->verifyRazorpaySignature($order, $payment, $validSig . '00') === false);
check('Wrong payment id verifies false', $svc->verifyRazorpaySignature($order, 'pay_OTHER', $validSig) === false);
check('Empty signature verifies false', $svc->verifyRazorpaySignature($order, $payment, '') === false);

section('Webhook HMAC verification');
$payload = '{"event":"payment.captured","id":"pay_XYZ"}';
$goodHook = hash_hmac('sha256', $payload, $WEBHOOK);
check('Valid webhook signature verifies true', $svc->verifyWebhookSignature('razorpay', $payload, $goodHook) === true);
check('Webhook signed with wrong secret verifies false', $svc->verifyWebhookSignature('razorpay', $payload, hash_hmac('sha256', $payload, 'wrong')) === false);

// ---------------------------------------------------------------------------
section('Ledger idempotency + duplicate-payment protection');
$sid = (int) QueryBuilder::table('students')->value('id') ?: null;
$t1 = $svc->recordOrder('razorpay', 'order_DUP1', 1500.00, $sid, null, 'test');
$t2 = $svc->recordOrder('razorpay', 'order_DUP1', 1500.00, $sid, null, 'test');
check('recordOrder is idempotent (same id for same order)', $t1 === $t2);
$dbDup = false;
try {
    QueryBuilder::table('payment_transactions')->insert(['gateway' => 'razorpay', 'gateway_order_id' => 'order_DUP1', 'amount' => 1, 'status' => 'pending']);
} catch (\Throwable) { $dbDup = true; }
check('DB UNIQUE(gateway,order_id) blocks duplicate order row', $dbDup === true);

$r1 = $svc->markVerified($t1, 'pay_DUP1', 'successful', 'upi');
check('First settlement succeeds', $r1['ok'] === true);
$row = QueryBuilder::table('payment_transactions')->where('id', $t1)->first();
check('Transaction marked successful + signature_verified', $row['status'] === 'successful' && (int) $row['signature_verified'] === 1);
$r2 = $svc->markVerified($t1, 'pay_DUP1', 'successful', 'upi');
check('Re-settlement is an idempotent no-op', str_contains(strtolower($r2['message']), 'idempotent'));

echo "\n\033[36m==================== RESULT ====================\033[0m\n";
echo "PASS: {$pass}   FAIL: {$fail}\n";
if ($fail > 0) { echo "\nFailures:\n - " . implode("\n - ", $failures) . "\n"; exit(1); }
echo "\033[32mAll payment-gateway checks passed.\033[0m\n";
exit(0);
