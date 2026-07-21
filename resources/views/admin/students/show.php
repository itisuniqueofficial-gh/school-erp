<?php
/** @var \Core\View\View $this */
/** @var array<string,mixed> $student */
/** @var array<int,array<string,mixed>> $guardians */
/** @var array<int,array<string,mixed>> $documents */
$name = $student['first_name'] . ' ' . $student['last_name'];
?>
<?php $this->extend('layouts.app'); ?>

<?php $this->section('breadcrumb'); ?><a href="<?= e(url('/admin/students')) ?>" class="hover:text-brand-600">Students</a> / Profile<?php $this->endSection(); ?>

<?php $this->section('page_actions'); ?>
<?php if (auth()->can('students.update')): ?>
<a href="<?= e(url('/admin/students/' . $student['id'] . '/edit')) ?>" class="btn btn-secondary"><i class="fa-solid fa-pen"></i> Edit</a>
<?php endif; ?>
<?php $this->endSection(); ?>

<?php $this->section('main'); ?>
<div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
    <!-- Profile card -->
    <div class="card p-6 text-center">
        <?php if (!empty($student['photo'])): ?>
            <img src="<?= e(asset('storage/uploads/students/' . $student['photo'])) ?>" alt="<?= e($name) ?>" class="mx-auto h-28 w-28 rounded-full object-cover">
        <?php else: ?>
            <span class="mx-auto flex h-28 w-28 items-center justify-center rounded-full bg-brand-600 text-3xl font-bold text-white"><?= e(strtoupper(substr((string) $student['first_name'], 0, 1))) ?></span>
        <?php endif; ?>
        <h2 class="mt-4 text-lg font-bold text-slate-900"><?= e($name) ?></h2>
        <p class="text-sm text-slate-500"><?= e((string) $student['admission_no']) ?></p>
        <span class="badge <?= $student['status'] === 'active' ? 'badge-green' : 'badge-slate' ?> mt-2"><?= e(ucfirst((string) $student['status'])) ?></span>
    </div>

    <!-- Details -->
    <div class="card p-6 lg:col-span-2">
        <h2 class="mb-4 font-semibold text-slate-800">Details</h2>
        <dl class="grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2">
            <?php
            $fields = [
                'Class' => $student['class_name'] ?? '—', 'Section' => $student['section_name'] ?? '—',
                'Roll No' => $student['roll_no'] ?? '—', 'Gender' => ucfirst((string) $student['gender']),
                'Date of Birth' => $student['dob'] ?? '—', 'Blood Group' => $student['blood_group'] ?? '—',
                'Email' => $student['email'] ?? '—', 'Phone' => $student['phone'] ?? '—',
                'Academic Year' => $student['year_name'] ?? '—', 'Address' => $student['address'] ?? '—',
            ];
            foreach ($fields as $label => $value):
            ?>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-400"><?= e($label) ?></dt>
                <dd class="text-sm text-slate-700"><?= e((string) $value) ?></dd>
            </div>
            <?php endforeach; ?>
        </dl>
    </div>

    <!-- Guardians -->
    <div class="card p-6 lg:col-span-2">
        <h2 class="mb-4 font-semibold text-slate-800">Guardians</h2>
        <?php if (empty($guardians)): ?>
            <p class="text-sm text-slate-400">No guardians recorded.</p>
        <?php else: ?>
        <ul class="divide-y divide-slate-100">
            <?php foreach ($guardians as $g): ?>
            <li class="flex items-center justify-between py-2">
                <div>
                    <p class="text-sm font-medium text-slate-800"><?= e((string) $g['name']) ?> <?php if (!empty($g['is_primary'])): ?><span class="badge badge-blue ml-1">Primary</span><?php endif; ?></p>
                    <p class="text-xs text-slate-500"><?= e(ucfirst((string) $g['relation'])) ?> · <?= e((string) ($g['phone'] ?? '—')) ?></p>
                </div>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>

    <!-- Documents -->
    <div class="card p-6">
        <h2 class="mb-4 font-semibold text-slate-800">Documents</h2>
        <?php if (empty($documents)): ?>
            <p class="text-sm text-slate-400">No documents uploaded.</p>
        <?php else: ?>
        <ul class="space-y-2">
            <?php foreach ($documents as $doc): ?>
            <li class="flex items-center gap-2 text-sm text-slate-700"><i class="fa-solid fa-file text-slate-400"></i> <?= e((string) $doc['title']) ?></li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>
</div>
<?php $this->endSection(); ?>
