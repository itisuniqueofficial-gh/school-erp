<?php /** @var \Core\View\View $this */ /** @var array<int,array<string,mixed>> $records */ ?>
<?php $this->extend('layouts.app'); ?>
<?php $this->section('main'); ?>
<div class="card overflow-hidden">
    <div class="overflow-x-auto">
        <table class="data-table">
            <thead><tr><th>Date</th><th>Status</th><th>Remarks</th></tr></thead>
            <tbody>
            <?php if (empty($records)): ?>
                <tr><td colspan="3" class="py-8 text-center text-slate-400">No attendance records yet.</td></tr>
            <?php else: foreach ($records as $r): ?>
                <?php $badge = in_array($r['status'], ['present','late','half_day'], true) ? 'badge-green' : ($r['status'] === 'absent' ? 'badge-red' : 'badge-slate'); ?>
                <tr>
                    <td><?= e((string) $r['date']) ?></td>
                    <td><span class="badge <?= $badge ?>"><?= e(ucfirst((string) $r['status'])) ?></span></td>
                    <td class="text-slate-500"><?= e((string) ($r['remarks'] ?? '')) ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php $this->endSection(); ?>
