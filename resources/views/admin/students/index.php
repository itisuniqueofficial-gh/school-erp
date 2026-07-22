<?php
/** @var \Core\View\View $this */
/** @var array<int,array<string,mixed>> $students */
/** @var array<string,int> $meta */
/** @var array<int,array<string,mixed>> $classes */
/** @var array{search:string,class_id:int,section_id:int,status:string} $filters */
?>
<?php $this->extend('layouts.app'); ?>

<?php $this->section('page_actions'); ?>
<?php if (auth()->can('students.create')): ?>
<a href="<?= e(url('/admin/students/create')) ?>" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Admit Student</a>
<?php endif; ?>
<?php $this->endSection(); ?>

<?php $this->section('main'); ?>
<!-- Filters -->
<form method="GET" action="<?= e(url('/admin/students')) ?>" class="card mb-4 p-4">
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">
        <input type="text" name="search" value="<?= e($filters['search']) ?>" placeholder="Search name / admission no"
               class="form-input lg:col-span-2">
        <select name="class_id" class="form-select">
            <option value="">All classes</option>
            <?php foreach ($classes as $c): ?>
                <option value="<?= (int) $c['id'] ?>" <?= $filters['class_id'] === (int) $c['id'] ? 'selected' : '' ?>><?= e((string) $c['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="status" class="form-select">
            <option value="">All statuses</option>
            <?php foreach (['active', 'inactive', 'graduated', 'transferred', 'alumni'] as $s): ?>
                <option value="<?= $s ?>" <?= $filters['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-secondary justify-center"><i class="fa-solid fa-filter"></i> Filter</button>
    </div>
</form>

<!-- Table -->
<form method="POST" action="<?= e(url('/admin/students/promote')) ?>" class="card overflow-hidden">
    <?= csrf_field() ?>
    <div class="overflow-x-auto">
        <table class="data-table" id="studentsTable">
            <thead>
                <tr>
                    <th class="w-8"><input type="checkbox" data-check-all></th>
                    <th>Admission No</th><th>Name</th><th>Class</th><th>Section</th><th>Gender</th><th>Status</th><th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($students)): ?>
                    <tr><td colspan="8" class="py-10 text-center text-slate-400">No students found.</td></tr>
                <?php else: foreach ($students as $s): ?>
                <tr>
                    <td><input type="checkbox" name="student_ids[]" value="<?= (int) $s['id'] ?>" class="row-check"></td>
                    <td class="font-mono text-xs"><?= e((string) $s['admission_no']) ?></td>
                    <td class="font-medium text-slate-800"><?= e((string) ($s['first_name'] . ' ' . $s['last_name'])) ?></td>
                    <td><?= e((string) ($s['class_name'] ?? '—')) ?></td>
                    <td><?= e((string) ($s['section_name'] ?? '—')) ?></td>
                    <td><?= e(ucfirst((string) $s['gender'])) ?></td>
                    <td><span class="badge <?= $s['status'] === 'active' ? 'badge-green' : 'badge-slate' ?>"><?= e(ucfirst((string) $s['status'])) ?></span></td>
                    <td class="text-right">
                        <a href="<?= e(url('/admin/students/' . $s['id'])) ?>" class="text-slate-500 hover:text-brand-600" aria-label="View"><i class="fa-solid fa-eye"></i></a>
                        <?php if (auth()->can('students.update')): ?>
                        <a href="<?= e(url('/admin/students/' . $s['id'] . '/edit')) ?>" class="ml-2 text-slate-500 hover:text-brand-600" aria-label="Edit"><i class="fa-solid fa-pen"></i></a>
                        <?php endif; ?>
                        <?php if (auth()->can('students.delete')): ?>
                        <button type="button" data-delete-url="<?= e(url('/admin/students/' . $s['id'])) ?>" data-confirm="Delete this student?"
                                class="ml-2 text-slate-500 hover:text-red-600" aria-label="Delete"><i class="fa-solid fa-trash"></i></button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <?php if (auth()->can('students.update') && !empty($students)): ?>
    <div class="flex flex-wrap items-center gap-2 border-t border-slate-200 bg-slate-50 px-4 py-3">
        <span class="text-sm text-slate-500">Bulk promote to:</span>
        <select name="to_class_id" class="form-select w-auto">
            <option value="">Select class</option>
            <?php foreach ($classes as $c): ?>
                <option value="<?= (int) $c['id'] ?>"><?= e((string) $c['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-secondary" data-confirm="Promote selected students?"><i class="fa-solid fa-arrow-up-right-dots"></i> Promote</button>

        <span class="ml-4 text-sm text-slate-500">Bulk action:</span>
        <select name="bulk_action" class="form-select w-auto">
            <option value="activate">Activate</option>
            <option value="deactivate">Deactivate</option>
            <?php if (auth()->can('students.delete')): ?>
            <option value="archive">Archive (soft-delete)</option>
            <option value="restore">Restore</option>
            <?php endif; ?>
        </select>
        <button type="submit" class="btn btn-secondary" formaction="<?= e(url('/admin/students/bulk')) ?>" data-confirm="Apply the selected bulk action to the checked students?"><i class="fa-solid fa-layer-group"></i> Apply</button>
    </div>
    <?php endif; ?>
</form>

<?php $this->include('partials.pagination', ['meta' => $meta]); ?>

<!-- Hidden delete form (method spoofing) -->
<form id="deleteForm" method="POST" class="hidden"><?= csrf_field() ?><?= method_field('DELETE') ?></form>
<?php $this->endSection(); ?>

<?php $this->section('page_scripts'); ?>
<script>
    $(function () {
        $('[data-check-all]').on('change', function () { $('.row-check').prop('checked', this.checked); });
        $('[data-delete-url]').on('click', function () {
            if (window.confirm($(this).data('confirm') || 'Delete?')) {
                const f = document.getElementById('deleteForm');
                f.action = $(this).data('delete-url');
                f.submit();
            }
        });
    });
</script>
<?php $this->endSection(); ?>
