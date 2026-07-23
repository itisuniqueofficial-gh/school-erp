<?php
/** @var \Core\View\View $this */
/** @var array<int,array<string,mixed>> $tickets */
/** @var array<string,string> $types */
?>
<?php $this->extend('layouts.app'); ?>
<?php $this->section('breadcrumb'); ?>Hall Tickets<?php $this->endSection(); ?>
<?php $this->section('main'); ?>
<div class="card overflow-hidden">
    <div class="border-b border-slate-200 px-5 py-4">
        <h2 class="font-semibold text-slate-800">My Hall Tickets / Admit Cards</h2>
        <p class="text-sm text-slate-500">Download or verify your exam entry documents. Revoked tickets cannot be downloaded.</p>
    </div>
    <div class="overflow-x-auto">
        <table class="data-table">
            <thead><tr><th>Ticket ID</th><th>Type</th><th>Exam</th><th>Academic Year</th><th>Status</th><th class="text-right">Actions</th></tr></thead>
            <tbody>
            <?php if (empty($tickets)): ?>
                <tr><td colspan="6" class="py-8 text-center text-slate-400">No hall tickets available yet.</td></tr>
            <?php else: foreach ($tickets as $t): ?>
                <tr>
                    <td class="font-mono text-xs"><?= e((string) $t['ht_uid']) ?></td>
                    <td><?= e($types[$t['type']] ?? ucfirst((string) $t['type'])) ?></td>
                    <td class="font-medium text-slate-800"><?= e((string) ($t['exam_name'] ?? '—')) ?></td>
                    <td><?= e((string) ($t['year_name'] ?? '—')) ?></td>
                    <td><span class="badge <?= $t['status'] === 'issued' ? 'badge-green' : ($t['status'] === 'revoked' ? 'badge-red' : 'badge-amber') ?>"><?= e(ucfirst((string) $t['status'])) ?></span></td>
                    <td class="text-right whitespace-nowrap">
                        <a href="<?= e(url('/student/hall-tickets/' . $t['ht_uid'])) ?>" class="text-slate-600 hover:underline"><i class="fa-solid fa-eye"></i> View</a>
                        <?php if ($t['status'] !== 'revoked'): ?>
                            <a href="<?= e(url('/student/hall-tickets/' . $t['ht_uid'] . '/download')) ?>" class="ml-2 text-brand-600 hover:underline"><i class="fa-solid fa-file-arrow-down"></i> PDF</a>
                        <?php endif; ?>
                        <a href="<?= e(url('/verify/hall-ticket/' . $t['ht_uid'])) ?>" target="_blank" rel="noopener" class="ml-2 text-slate-600 hover:underline"><i class="fa-solid fa-qrcode"></i> Verify</a>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php $this->endSection(); ?>
