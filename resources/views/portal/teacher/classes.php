<?php
/** @var \Core\View\View $this */
/** @var array<int,array<string,mixed>> $assignments */
/** @var array<int,array<string,mixed>> $classTeacherOf */
?>
<?php $this->extend('layouts.app'); ?>
<?php $this->section('main'); ?>
<div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
    <div class="card p-5">
        <h2 class="mb-4 font-semibold text-slate-800">Subject assignments</h2>
        <?php if (empty($assignments)): ?><p class="text-sm text-slate-400">No assignments.</p><?php else: ?>
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead><tr><th>Class</th><th>Section</th><th>Subject</th><th>Role</th></tr></thead>
                <tbody>
                <?php foreach ($assignments as $a): ?>
                    <tr>
                        <td><?= e((string) ($a['class_name'] ?? '—')) ?></td>
                        <td><?= e((string) ($a['section_name'] ?? '—')) ?></td>
                        <td class="font-medium text-slate-800"><?= e((string) ($a['subject_name'] ?? '—')) ?></td>
                        <td><span class="badge badge-slate"><?= e(str_replace('_', ' ', (string) $a['role'])) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
    <div class="card p-5">
        <h2 class="mb-4 font-semibold text-slate-800">Class teacher of</h2>
        <?php if (empty($classTeacherOf)): ?><p class="text-sm text-slate-400">Not a class teacher.</p><?php else: ?>
        <div class="flex flex-wrap gap-2">
            <?php foreach ($classTeacherOf as $s): ?>
                <a href="<?= e(url('/teacher/attendance?class_id=' . (int) $s['class_id'] . '&section_id=' . (int) $s['section_id'])) ?>" class="badge badge-green"><?= e((string) ($s['class_name'] . ' - ' . $s['section_name'])) ?></a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php $this->endSection(); ?>
