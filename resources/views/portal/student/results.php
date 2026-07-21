<?php /** @var \Core\View\View $this */ /** @var array<int,array<string,mixed>> $results */ ?>
<?php $this->extend('layouts.app'); ?>
<?php $this->section('main'); ?>
<div class="card overflow-hidden">
    <div class="overflow-x-auto">
        <table class="data-table">
            <thead><tr><th>Exam</th><th>Obtained</th><th>Total</th><th>Percentage</th><th>GPA</th><th>Result</th></tr></thead>
            <tbody>
            <?php if (empty($results)): ?>
                <tr><td colspan="6" class="py-8 text-center text-slate-400">No results published yet.</td></tr>
            <?php else: foreach ($results as $r): ?>
                <tr>
                    <td class="font-medium text-slate-800"><?= e((string) ($r['exam_name'] ?? '—')) ?></td>
                    <td><?= e((string) $r['obtained_marks']) ?></td>
                    <td><?= e((string) $r['total_marks']) ?></td>
                    <td><?= e((string) $r['percentage']) ?>%</td>
                    <td><?= e((string) $r['gpa']) ?></td>
                    <td><span class="badge <?= ($r['result'] ?? '') === 'pass' ? 'badge-green' : (($r['result'] ?? '') === 'fail' ? 'badge-red' : 'badge-amber') ?>"><?= e(ucfirst((string) ($r['result'] ?? 'pending'))) ?></span></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php $this->endSection(); ?>
