<?php
/** @var \Core\View\View $this */
/** @var array<string,array{meta:array,config:array}> $gateways */
/** @var array<int,array<string,mixed>> $transactions */
$statusBadge = [
    'successful' => 'badge-green', 'pending' => 'badge-slate', 'processing' => 'badge-blue',
    'failed' => 'badge-red', 'cancelled' => 'badge-red', 'refunded' => 'badge-amber', 'partially_refunded' => 'badge-amber',
];
?>
<?php $this->extend('layouts.app'); ?>
<?php $this->section('breadcrumb'); ?><a href="<?= e(url('/admin/settings')) ?>" class="hover:text-brand-600">Settings</a> / Payment Gateways<?php $this->endSection(); ?>
<?php $this->section('main'); ?>

<div class="mb-4 rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800">
    <i class="fa-solid fa-shield-halved mr-1"></i>
    Credentials are <strong>encrypted at rest</strong> and never shown in full. Leave a secret field blank to keep the saved value. Payments are always verified server-side.
</div>

<div class="grid gap-6 lg:grid-cols-3">
    <?php foreach ($gateways as $gw => $data): $c = $data['config']; $meta = $data['meta']; ?>
    <div class="card p-5">
        <div class="mb-3 flex items-center justify-between">
            <h2 class="font-semibold text-slate-800"><?= e($meta['label']) ?></h2>
            <span class="badge <?= !empty($c['enabled']) ? 'badge-green' : 'badge-slate' ?>"><?= !empty($c['enabled']) ? 'Enabled' : 'Disabled' ?></span>
        </div>
        <form method="POST" action="<?= e(url('/admin/settings/payments/' . $gw)) ?>" class="space-y-3" data-loading>
            <?= csrf_field() ?>
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="enabled" value="1" <?= !empty($c['enabled']) ? 'checked' : '' ?> class="rounded border-slate-300">
                Enable this gateway
            </label>
            <div>
                <label class="form-label" for="<?= $gw ?>_mode">Mode</label>
                <select id="<?= $gw ?>_mode" name="mode" class="form-select">
                    <option value="test" <?= ($c['mode'] ?? 'test') === 'test' ? 'selected' : '' ?>>Test</option>
                    <option value="live" <?= ($c['mode'] ?? 'test') === 'live' ? 'selected' : '' ?>>Live</option>
                </select>
            </div>
            <?php foreach ($meta['fields'] as $field):
                $isSecret = in_array($field, $meta['secret'], true);
                $isSet = !empty($c[$field . '_set']); ?>
            <div>
                <label class="form-label" for="<?= $gw . '_' . $field ?>"><?= e(ucwords(str_replace('_', ' ', $field))) ?><?php if ($isSecret): ?> <span class="text-xs text-slate-400">(secret)</span><?php endif; ?></label>
                <input id="<?= $gw . '_' . $field ?>" name="<?= e($field) ?>" type="<?= $isSecret ? 'password' : 'text' ?>"
                       class="form-input" autocomplete="off"
                       value="<?= $isSecret ? '' : e((string) ($c[$field] ?? '')) ?>"
                       placeholder="<?= $isSecret && $isSet ? '•••••••• (saved — leave blank to keep)' : '' ?>">
            </div>
            <?php endforeach; ?>
            <button class="btn btn-primary w-full justify-center"><i class="fa-solid fa-floppy-disk"></i> Save <?= e($meta['label']) ?></button>
        </form>
    </div>
    <?php endforeach; ?>
</div>

<div class="card mt-6 overflow-hidden">
    <div class="border-b border-slate-100 px-5 py-3"><h2 class="font-semibold text-slate-800">Recent transactions</h2></div>
    <div class="overflow-x-auto">
        <table class="data-table">
            <thead><tr><th>Txn ID</th><th>Student</th><th>Gateway</th><th>Amount</th><th>Status</th><th>Verified</th><th>Date</th></tr></thead>
            <tbody>
            <?php if (empty($transactions)): ?>
                <tr><td colspan="7" class="py-8 text-center text-slate-400">No transactions recorded yet.</td></tr>
            <?php else: foreach ($transactions as $t): ?>
                <tr>
                    <td class="font-mono text-xs"><?= e((string) ($t['uid'] ?? '—')) ?></td>
                    <td><?= e(trim((string) (($t['first_name'] ?? '') . ' ' . ($t['last_name'] ?? ''))) ?: '—') ?></td>
                    <td><?= e(ucfirst((string) $t['gateway'])) ?> <span class="text-xs text-slate-400">(<?= e((string) ($t['mode'] ?? 'test')) ?>)</span></td>
                    <td>₹<?= e(number_format((float) $t['amount'], 2)) ?></td>
                    <td><span class="badge <?= $statusBadge[$t['status']] ?? 'badge-slate' ?>"><?= e(ucfirst(str_replace('_', ' ', (string) $t['status']))) ?></span></td>
                    <td><?= !empty($t['signature_verified']) ? '<i class="fa-solid fa-circle-check text-green-600"></i>' : '<i class="fa-solid fa-circle-xmark text-slate-300"></i>' ?></td>
                    <td class="text-xs text-slate-500"><?= e((string) ($t['created_at'] ?? '')) ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php $this->endSection(); ?>
