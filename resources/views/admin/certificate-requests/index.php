<?php
/** @var \Core\View\View $this */
/** @var array<int,array<string,mixed>> $requests */
/** @var array<string,string> $labels */
/** @var array<string,string> $filters */
$badge = static function (string $s): string {
    return match ($s) {
        'generated', 'approved' => 'badge-green',
        'rejected', 'cancelled' => 'badge-red',
        default => 'badge-amber',
    };
};
?>
<?php $this->extend('layouts.app'); ?>
<?php $this->section('breadcrumb'); ?>Certificate Requests<?php $this->endSection(); ?>
<?php $this->section('main'); ?>
<div class="card overflow-hidden">
    <form method="GET" class="flex flex-wrap items-end gap-3 border-b border-slate-200 px-5 py-4">
        <div>
            <label class="block text-xs font-semibold text-slate-500">Status</label>
            <select name="status" class="form-input">
                <option value="">All</option>
                <?php foreach ($labels as $k => $label): ?>
                    <option value="<?= e($k) ?>" <?= ($filters['status'] ?? '') === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button class="btn btn-secondary"><i class="fa-solid fa-filter"></i> Filter</button>
    </form>
    <div class="overflow-x-auto">
        <table class="data-table">
            <thead><tr><th>Request ID</th><th>Student</th><th>Certificate</th><th>Purpose</th><th>Status</th><th class="text-right">Review</th></tr></thead>
            <tbody>
            <?php if (empty($requests)): ?>
                <tr><td colspan="6" class="py-8 text-center text-slate-400">No certificate requests.</td></tr>
            <?php else: foreach ($requests as $r): ?>
                <tr>
                    <td class="font-mono text-xs"><?= e((string) $r['req_uid']) ?></td>
                    <td class="font-medium text-slate-800"><?= e(trim((string) (($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? '')))) ?><div class="text-xs text-slate-400"><?= e((string) $r['admission_no']) ?></div></td>
                    <td><?= e(ucfirst((string) $r['type'])) ?></td>
                    <td class="text-slate-500"><?= e((string) ($r['purpose'] ?? '—')) ?><?php if (!empty($r['reason'])): ?><div class="text-xs text-slate-400"><?= e((string) $r['reason']) ?></div><?php endif; ?></td>
                    <td><span class="badge <?= $badge((string) $r['status']) ?>"><?= e($labels[$r['status']] ?? ucfirst((string) $r['status'])) ?></span>
                        <?php if (!empty($r['cert_uid'])): ?><div class="mt-0.5 font-mono text-xs text-slate-400"><?= e((string) $r['cert_uid']) ?></div><?php endif; ?>
                    </td>
                    <td class="text-right whitespace-nowrap">
                        <?php if (in_array($r['status'], ['submitted', 'under_review', 'changes_requested'], true) && auth()->can('certificates.manage')): ?>
                            <form method="POST" action="<?= e(url('/admin/certificate-requests/' . $r['req_uid'] . '/approve')) ?>" class="inline" data-confirm="Approve and generate the certificate from verified data?">
                                <?= csrf_field() ?><button class="text-green-600 hover:underline"><i class="fa-solid fa-check"></i> Approve</button>
                            </form>
                            <form method="POST" action="<?= e(url('/admin/certificate-requests/' . $r['req_uid'] . '/reject')) ?>" class="ml-2 inline" onsubmit="var n=prompt('Reason for rejection (optional):','');if(n===null)return false;this.note.value=n;">
                                <?= csrf_field() ?><input type="hidden" name="note"><button class="text-red-600 hover:underline"><i class="fa-solid fa-xmark"></i> Reject</button>
                            </form>
                        <?php elseif ($r['status'] === 'generated' && !empty($r['cert_uid'])): ?>
                            <a href="<?= e(url('/admin/certificates/' . $r['cert_uid'])) ?>" class="text-brand-600 hover:underline"><i class="fa-solid fa-eye"></i> View Certificate</a>
                        <?php else: ?>
                            <span class="text-slate-300">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php $this->endSection(); ?>
