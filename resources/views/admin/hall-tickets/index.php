<?php
/** @var \Core\View\View $this */
/** @var array<int,array<string,mixed>> $tickets */
/** @var array<string,string> $types */
/** @var array<int,array<string,mixed>> $exams */
/** @var array<string,mixed> $filters */
$badge = static fn (string $s): string => match ($s) {
    'issued', 'approved' => 'badge-green', 'revoked', 'expired' => 'badge-red', default => 'badge-amber',
};
?>
<?php $this->extend('layouts.app'); ?>
<?php $this->section('breadcrumb'); ?><a href="<?= e(url('/admin/exams')) ?>" class="hover:text-brand-600">Examinations</a> / Hall Tickets<?php $this->endSection(); ?>
<?php $this->section('page_actions'); ?>
<a href="<?= e(url('/admin/hall-tickets/generate')) ?>" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Generate</a>
<?php $this->endSection(); ?>
<?php $this->section('main'); ?>
<div class="card overflow-hidden">
    <form method="GET" class="flex flex-wrap items-end gap-3 border-b border-slate-200 px-5 py-4">
        <div>
            <label class="block text-xs font-semibold text-slate-500">Exam</label>
            <select name="exam_id" class="form-input">
                <option value="">All</option>
                <?php foreach ($exams as $ex): ?>
                    <option value="<?= (int) $ex['id'] ?>" <?= (int) ($filters['exam_id'] ?? 0) === (int) $ex['id'] ? 'selected' : '' ?>><?= e((string) $ex['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-xs font-semibold text-slate-500">Type</label>
            <select name="type" class="form-input">
                <option value="">All</option>
                <?php foreach ($types as $k => $lbl): ?><option value="<?= e($k) ?>" <?= ($filters['type'] ?? '') === $k ? 'selected' : '' ?>><?= e($lbl) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-xs font-semibold text-slate-500">Status</label>
            <select name="status" class="form-input">
                <option value="">All</option>
                <?php foreach (['issued', 'revoked', 'expired', 'draft', 'pending', 'approved'] as $st): ?><option value="<?= $st ?>" <?= ($filters['status'] ?? '') === $st ? 'selected' : '' ?>><?= ucfirst($st) ?></option><?php endforeach; ?>
            </select>
        </div>
        <button class="btn btn-secondary"><i class="fa-solid fa-filter"></i> Filter</button>
    </form>
    <div class="overflow-x-auto">
        <table class="data-table">
            <thead><tr><th>Ticket ID</th><th>Type</th><th>Student</th><th>Exam</th><th>Class</th><th>Status</th><th class="text-right">Actions</th></tr></thead>
            <tbody>
            <?php if (empty($tickets)): ?>
                <tr><td colspan="7" class="py-8 text-center text-slate-400">No hall tickets issued yet.</td></tr>
            <?php else: foreach ($tickets as $t): ?>
                <tr>
                    <td class="font-mono text-xs"><?= e((string) $t['ht_uid']) ?></td>
                    <td><?= e($types[$t['type']] ?? ucfirst((string) $t['type'])) ?></td>
                    <td class="font-medium text-slate-800"><?= e(trim((string) (($t['first_name'] ?? '') . ' ' . ($t['last_name'] ?? '')))) ?><div class="text-xs text-slate-400"><?= e((string) $t['admission_no']) ?></div></td>
                    <td><?= e((string) ($t['exam_name'] ?? '—')) ?></td>
                    <td><?= e((string) ($t['class_name'] ?? '—')) ?></td>
                    <td><span class="badge <?= $badge((string) $t['status']) ?>"><?= e(ucfirst((string) $t['status'])) ?></span></td>
                    <td class="text-right whitespace-nowrap">
                        <a href="<?= e(url('/admin/hall-tickets/' . $t['ht_uid'])) ?>" class="text-slate-600 hover:underline"><i class="fa-solid fa-eye"></i> View</a>
                        <a href="<?= e(url('/admin/hall-tickets/' . $t['ht_uid'] . '/download')) ?>" class="ml-2 text-brand-600 hover:underline"><i class="fa-solid fa-file-arrow-down"></i> PDF</a>
                        <a href="<?= e(url('/verify/hall-ticket/' . $t['ht_uid'])) ?>" target="_blank" rel="noopener" class="ml-2 text-slate-600 hover:underline"><i class="fa-solid fa-qrcode"></i> Verify</a>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php $this->endSection(); ?>
