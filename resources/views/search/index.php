<?php
/** @var \Core\View\View $this */
/** @var string $q */
/** @var array<string,array<int,array<string,mixed>>> $results */
/** @var int $total */
?>
<?php $this->extend('layouts.app'); ?>
<?php $this->section('main'); ?>
<form method="GET" action="<?= e(url('/search')) ?>" class="card mb-4 p-4">
    <div class="flex gap-2">
        <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search students, teachers, classes, subjects, exams…" class="form-input flex-1" autofocus>
        <button class="btn btn-primary"><i class="fa-solid fa-magnifying-glass"></i> Search</button>
    </div>
</form>

<?php if ($q === ''): ?>
    <div class="card p-8 text-center text-slate-400">Type at least 2 characters to search.</div>
<?php elseif ($total === 0): ?>
    <div class="card p-8 text-center text-slate-400">No results for “<?= e($q) ?>”.</div>
<?php else: ?>
<div class="space-y-6">
    <?php
    $cats = [
        'students' => ['Students', 'fa-user-graduate', '/admin/students/'],
        'teachers' => ['Teachers', 'fa-chalkboard-user', '/admin/teachers/'],
        'classes'  => ['Classes', 'fa-school', '/admin/academic/classes'],
        'subjects' => ['Subjects', 'fa-book', '/admin/academic/subjects'],
        'exams'    => ['Exams', 'fa-award', '/admin/exams'],
    ];
    foreach ($cats as $key => [$label, $icon, $base]):
        $rows = $results[$key] ?? [];
        if (empty($rows)) continue; ?>
    <div class="card p-5">
        <h2 class="mb-3 font-semibold text-slate-800"><i class="fa-solid <?= $icon ?> mr-1 text-slate-400"></i> <?= e($label) ?> (<?= count($rows) ?>)</h2>
        <ul class="divide-y divide-slate-100">
            <?php foreach ($rows as $r):
                $isProfile = in_array($key, ['students', 'teachers'], true);
                $href = $isProfile ? $base . (int) $r['id'] : $base;
                $primary = trim((string) (($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? ''))) ?: (string) ($r['name'] ?? '');
                $meta = (string) ($r['public_id'] ?? $r['admission_no'] ?? $r['employee_code'] ?? $r['code'] ?? '');
            ?>
            <li><a href="<?= e(url($href)) ?>" class="flex items-center justify-between py-2 text-sm hover:text-brand-600">
                <span class="font-medium text-slate-700"><?= e($primary) ?></span>
                <span class="font-mono text-xs text-slate-400"><?= e($meta) ?></span>
            </a></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
<?php $this->endSection(); ?>
