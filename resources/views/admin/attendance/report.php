<?php
/** @var \Core\View\View $this */
/** @var array<int,array<string,mixed>> $classes */
/** @var array<int,array<string,mixed>> $sections */
/** @var array<int,array<string,mixed>> $students */
/** @var array<int,array<int,string>> $grid */
/** @var int $daysInMonth */
/** @var array{class_id:int,section_id:int,month:string} $selected */
$abbr = ['present' => 'P', 'absent' => 'A', 'late' => 'L', 'half_day' => 'H', 'leave' => 'Lv', 'holiday' => 'Ho'];
$tone = ['present' => 'text-green-600', 'absent' => 'text-red-600', 'late' => 'text-amber-600', 'half_day' => 'text-blue-600', 'leave' => 'text-slate-500', 'holiday' => 'text-slate-400'];
?>
<?php $this->extend('layouts.app'); ?>
<?php $this->section('main'); ?>
<form method="GET" class="card mb-4 p-4">
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-4">
        <select name="class_id" class="form-select"><option value="">Class</option><?php foreach ($classes as $c): ?><option value="<?= (int) $c['id'] ?>" <?= $selected['class_id'] === (int) $c['id'] ? 'selected' : '' ?>><?= e((string) $c['name']) ?></option><?php endforeach; ?></select>
        <select name="section_id" class="form-select"><option value="">Section</option><?php foreach ($sections as $s): ?><option value="<?= (int) $s['id'] ?>" <?= $selected['section_id'] === (int) $s['id'] ? 'selected' : '' ?>><?= e((string) ($s['class_name'] . ' - ' . $s['name'])) ?></option><?php endforeach; ?></select>
        <input type="month" name="month" value="<?= e($selected['month']) ?>" class="form-input">
        <button class="btn btn-primary justify-center"><i class="fa-solid fa-magnifying-glass"></i> View</button>
    </div>
</form>

<?php if (!empty($students)): ?>
<div class="card overflow-x-auto">
    <table class="data-table text-xs">
        <thead>
            <tr>
                <th class="sticky left-0 bg-white">Student</th>
                <?php for ($d = 1; $d <= $daysInMonth; $d++): ?><th class="text-center"><?= $d ?></th><?php endfor; ?>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($students as $st): ?>
            <tr>
                <td class="sticky left-0 bg-white font-medium text-slate-800 whitespace-nowrap"><?= e((string) ($st['first_name'] . ' ' . $st['last_name'])) ?></td>
                <?php for ($d = 1; $d <= $daysInMonth; $d++):
                    $status = $grid[(int) $st['id']][$d] ?? null;
                ?>
                    <td class="text-center font-semibold <?= $status ? ($tone[$status] ?? '') : 'text-slate-200' ?>"><?= $status ? e($abbr[$status] ?? '?') : '·' ?></td>
                <?php endfor; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<p class="mt-3 text-xs text-slate-500">Legend: P=Present, A=Absent, L=Late, H=Half day, Lv=Leave, Ho=Holiday</p>
<?php else: ?>
    <div class="card p-8 text-center text-slate-400">Select class, section and month to view the report.</div>
<?php endif; ?>
<?php $this->endSection(); ?>
