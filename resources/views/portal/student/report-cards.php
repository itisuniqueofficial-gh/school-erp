<?php /** @var \Core\View\View $this */ /** @var array<int,array<string,mixed>> $cards */ ?>
<?php $this->extend('layouts.app'); ?>
<?php $this->section('main'); ?>
<div class="card overflow-hidden">
    <div class="border-b border-slate-200 px-5 py-4">
        <h2 class="font-semibold text-slate-800">My Report Cards</h2>
        <p class="text-sm text-slate-500">View, download or verify your report cards. You can only access your own records.</p>
    </div>
    <div class="overflow-x-auto">
        <table class="data-table">
            <thead><tr><th>Exam</th><th>Academic Year</th><th>Report Card ID</th><th>%</th><th>Result</th><th class="text-right">Actions</th></tr></thead>
            <tbody>
            <?php if (empty($cards)): ?>
                <tr><td colspan="6" class="py-8 text-center text-slate-400">No report cards published yet.</td></tr>
            <?php else: foreach ($cards as $c): ?>
                <tr>
                    <td class="font-medium text-slate-800"><?= e((string) ($c['exam_name'] ?? '—')) ?></td>
                    <td><?= e((string) ($c['year_name'] ?? '—')) ?></td>
                    <td class="font-mono text-xs"><?= e((string) ($c['rc_uid'] ?? '—')) ?> <span class="text-slate-400">v<?= (int) ($c['version'] ?? 1) ?></span></td>
                    <td><?= e((string) ($c['percentage'] ?? 0)) ?>%</td>
                    <td><span class="badge <?= ($c['result'] ?? '') === 'pass' ? 'badge-green' : (($c['result'] ?? '') === 'fail' ? 'badge-red' : 'badge-amber') ?>"><?= e(ucfirst((string) ($c['result'] ?? 'pending'))) ?></span></td>
                    <td class="text-right whitespace-nowrap">
                        <a href="<?= e(url('/student/report-cards/' . $c['rc_uid'])) ?>" class="text-slate-600 hover:underline"><i class="fa-solid fa-eye"></i> View</a>
                        <a href="<?= e(url('/student/report-cards/' . $c['rc_uid'] . '/download')) ?>" class="ml-2 text-brand-600 hover:underline"><i class="fa-solid fa-file-arrow-down"></i> PDF</a>
                        <a href="<?= e(url('/verify/report-card/' . $c['rc_uid'])) ?>" target="_blank" rel="noopener" class="ml-2 text-slate-600 hover:underline"><i class="fa-solid fa-qrcode"></i> Verify</a>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php $this->endSection(); ?>
