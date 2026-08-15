<?php
/** @var \Core\View\View $this */
/** @var array<int,array<string,mixed>> $certificates */
/** @var array<string,string> $types */
/** @var array<string,string> $filters */
?>
<?php $this->extend('layouts.app'); ?>
<?php $this->section('breadcrumb'); ?>Certificates<?php $this->endSection(); ?>
<?php $this->section('page_actions'); ?>
<a href="<?= e(url('/admin/certificates/create')) ?>" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Issue Certificate</a>
<?php $this->endSection(); ?>
<?php $this->section('main'); ?>
<div class="card overflow-hidden">
    <form method="GET" class="flex flex-wrap items-end gap-3 border-b border-slate-200 px-5 py-4">
        <div>
            <label class="block text-xs font-semibold text-slate-500">Type</label>
            <select name="type" class="form-input">
                <option value="">All types</option>
                <?php foreach ($types as $k => $label): ?>
                    <option value="<?= e($k) ?>" <?= ($filters['type'] ?? '') === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-xs font-semibold text-slate-500">Status</label>
            <select name="status" class="form-input">
                <option value="">All</option>
                <option value="issued" <?= ($filters['status'] ?? '') === 'issued' ? 'selected' : '' ?>>Issued</option>
                <option value="revoked" <?= ($filters['status'] ?? '') === 'revoked' ? 'selected' : '' ?>>Revoked</option>
            </select>
        </div>
        <button class="btn btn-secondary"><i class="fa-solid fa-filter"></i> Filter</button>
    </form>
    <div class="overflow-x-auto">
        <table class="data-table">
            <thead><tr><th>Certificate ID</th><th>Type</th><th>Student</th><th>Admission</th><th>Issued</th><th>Status</th><th class="text-right">Actions</th></tr></thead>
            <tbody>
            <?php if (empty($certificates)): ?>
                <tr><td colspan="7" class="py-8 text-center text-slate-400">No certificates issued yet.</td></tr>
            <?php else: foreach ($certificates as $c): ?>
                <tr>
                    <td class="font-mono text-xs"><?= e((string) $c['cert_uid']) ?></td>
                    <td><?= e((string) ($types[$c['type']] ?? ucfirst((string) $c['type']))) ?></td>
                    <td class="font-medium text-slate-800"><?= e(trim((string) (($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? '')))) ?></td>
                    <td class="font-mono text-xs"><?= e((string) $c['admission_no']) ?></td>
                    <td><?= e((string) $c['issued_on']) ?></td>
                    <td><span class="badge <?= $c['status'] === 'issued' ? 'badge-green' : 'badge-red' ?>"><?= e(ucfirst((string) $c['status'])) ?></span></td>
                    <td class="text-right">
                        <div class="action-group action-end">
                            <a href="<?= e(url('/admin/certificates/' . $c['cert_uid'])) ?>" class="action-link text-slate-600" aria-label="View certificate"><i class="fa-solid fa-eye"></i> View</a>
                            <a href="<?= e(url('/admin/certificates/' . $c['cert_uid'] . '/download')) ?>" class="action-link text-brand-600" aria-label="Download certificate PDF"><i class="fa-solid fa-file-arrow-down"></i> PDF</a>
                            <a href="<?= e(url('/verify/certificate/' . $c['cert_uid'])) ?>" target="_blank" rel="noopener" class="action-link text-slate-600" aria-label="Verify certificate"><i class="fa-solid fa-qrcode"></i> Verify</a>
                        </div>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php $this->endSection(); ?>
