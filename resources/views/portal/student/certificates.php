<?php
/** @var \Core\View\View $this */
/** @var array<int,array<string,mixed>> $requests */
/** @var array<int,array<string,mixed>> $issued */
/** @var array<string,string> $types */
/** @var array<int,string> $requestable */
/** @var array<int,string> $purposes */
/** @var array<string,string> $labels */
$badge = static function (string $s): string {
    return match ($s) {
        'generated', 'approved' => 'badge-green',
        'rejected', 'cancelled' => 'badge-red',
        default => 'badge-amber',
    };
};
?>
<?php $this->extend('layouts.app'); ?>
<?php $this->section('breadcrumb'); ?>Documents &amp; Certificates<?php $this->endSection(); ?>
<?php $this->section('main'); ?>
<div class="space-y-6">
    <!-- Request a certificate -->
    <div class="card">
        <div class="border-b border-slate-200 px-5 py-4">
            <h2 class="font-semibold text-slate-800">Request a Certificate</h2>
            <p class="text-sm text-slate-500">Choose a certificate and tell us why you need it. Your official details are filled in automatically after approval — you cannot edit them.</p>
        </div>
        <form method="POST" action="<?= e(url('/student/certificates/request')) ?>" class="grid grid-cols-1 gap-4 p-5 sm:grid-cols-2">
            <?= csrf_field() ?>
            <div>
                <label class="block text-sm font-semibold text-slate-700">Certificate <span class="text-red-500">*</span></label>
                <select name="type" required class="form-input">
                    <?php foreach ($requestable as $t): ?>
                        <option value="<?= e($t) ?>"><?= e($types[$t] ?? ucfirst($t)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700">Purpose <span class="text-red-500">*</span></label>
                <select name="purpose" required class="form-input" onchange="document.getElementById('otherPurpose').style.display = this.value === 'Other' ? 'block' : 'none';">
                    <?php foreach ($purposes as $p): ?>
                        <option value="<?= e($p) ?>"><?= e($p) ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="text" name="other_purpose" id="otherPurpose" style="display:none;" class="form-input mt-2" placeholder="Please specify the purpose">
            </div>
            <div class="sm:col-span-2">
                <label class="block text-sm font-semibold text-slate-700">Additional details <span class="text-slate-400">(optional)</span></label>
                <textarea name="reason" rows="2" class="form-input" placeholder="e.g. scholarship name, bank name, or any note for the office"></textarea>
            </div>
            <div class="sm:col-span-2">
                <button class="btn btn-primary"><i class="fa-solid fa-paper-plane"></i> Submit Request</button>
            </div>
        </form>
    </div>

    <!-- My requests -->
    <div class="card overflow-hidden">
        <div class="border-b border-slate-200 px-5 py-4"><h2 class="font-semibold text-slate-800">My Requests</h2></div>
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead><tr><th>Request ID</th><th>Certificate</th><th>Purpose</th><th>Status</th><th>Requested</th><th class="text-right">Actions</th></tr></thead>
                <tbody>
                <?php if (empty($requests)): ?>
                    <tr><td colspan="6" class="py-8 text-center text-slate-400">You have not requested any certificates yet.</td></tr>
                <?php else: foreach ($requests as $r): ?>
                    <tr>
                        <td class="font-mono text-xs"><?= e((string) $r['req_uid']) ?></td>
                        <td><?= e($types[$r['type']] ?? ucfirst((string) $r['type'])) ?></td>
                        <td class="text-slate-500"><?= e((string) ($r['purpose'] ?? '—')) ?></td>
                        <td><span class="badge <?= $badge((string) $r['status']) ?>"><?= e($labels[$r['status']] ?? ucfirst((string) $r['status'])) ?></span>
                            <?php if (!empty($r['review_note'])): ?><div class="mt-0.5 text-xs text-slate-400"><?= e((string) $r['review_note']) ?></div><?php endif; ?>
                        </td>
                        <td class="text-slate-500"><?= e(substr((string) ($r['created_at'] ?? ''), 0, 10)) ?></td>
                        <td class="text-right whitespace-nowrap">
                            <?php if ($r['status'] === 'generated' && !empty($r['cert_uid'])): ?>
                                <a href="<?= e(url('/student/certificates/' . $r['cert_uid'] . '/download')) ?>" class="text-brand-600 hover:underline"><i class="fa-solid fa-file-arrow-down"></i> Download</a>
                                <a href="<?= e(url('/verify/certificate/' . $r['cert_uid'])) ?>" target="_blank" rel="noopener" class="ml-2 text-slate-600 hover:underline"><i class="fa-solid fa-qrcode"></i> Verify</a>
                            <?php elseif (in_array($r['status'], ['submitted', 'under_review', 'changes_requested'], true)): ?>
                                <form method="POST" action="<?= e(url('/student/certificates/' . $r['req_uid'] . '/cancel')) ?>" class="inline" data-confirm="Cancel this request?">
                                    <?= csrf_field() ?><button class="text-red-600 hover:underline"><i class="fa-solid fa-xmark"></i> Cancel</button>
                                </form>
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

    <!-- Issued certificates -->
    <div class="card overflow-hidden">
        <div class="border-b border-slate-200 px-5 py-4"><h2 class="font-semibold text-slate-800">My Certificates</h2></div>
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead><tr><th>Certificate ID</th><th>Type</th><th>Issued</th><th>Status</th><th class="text-right">Actions</th></tr></thead>
                <tbody>
                <?php if (empty($issued)): ?>
                    <tr><td colspan="5" class="py-8 text-center text-slate-400">No certificates issued yet.</td></tr>
                <?php else: foreach ($issued as $c): ?>
                    <tr>
                        <td class="font-mono text-xs"><?= e((string) $c['cert_uid']) ?></td>
                        <td><?= e($types[$c['type']] ?? ucfirst((string) $c['type'])) ?></td>
                        <td class="text-slate-500"><?= e((string) $c['issued_on']) ?></td>
                        <td><span class="badge <?= $c['status'] === 'issued' ? 'badge-green' : 'badge-red' ?>"><?= e(ucfirst((string) $c['status'])) ?></span></td>
                        <td class="text-right whitespace-nowrap">
                            <a href="<?= e(url('/student/certificates/' . $c['cert_uid'] . '/download')) ?>" class="text-brand-600 hover:underline"><i class="fa-solid fa-file-arrow-down"></i> Download</a>
                            <a href="<?= e(url('/verify/certificate/' . $c['cert_uid'])) ?>" target="_blank" rel="noopener" class="ml-2 text-slate-600 hover:underline"><i class="fa-solid fa-qrcode"></i> Verify</a>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <p class="border-t border-slate-100 px-5 py-3 text-xs text-slate-400"><i class="fa-solid fa-circle-info"></i> After downloading and printing, you may need to obtain the authorised signature and/or official school stamp before submitting the document to an external authority.</p>
    </div>
</div>
<?php $this->endSection(); ?>
