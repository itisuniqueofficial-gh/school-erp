<?php
/** @var \Core\View\View $this */
/** @var array<int,array<string,mixed>> $teachers */
/** @var array<string,int> $meta */
/** @var array<string,mixed> $filters */
?>
<?php $this->extend('layouts.app'); ?>
<?php $this->section('page_actions'); ?>
<?php if (auth()->can('teachers.create')): ?>
<a href="<?= e(url('/admin/teachers/create')) ?>" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Add Teacher</a>
<?php endif; ?>
<?php $this->endSection(); ?>

<?php $this->section('main'); ?>
<form method="GET" class="card mb-4 p-4">
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
        <input type="text" name="search" value="<?= e((string) $filters['search']) ?>" placeholder="Search name / code / email" class="form-input sm:col-span-2">
        <button class="btn btn-secondary justify-center"><i class="fa-solid fa-filter"></i> Filter</button>
    </div>
</form>

<div class="card overflow-hidden">
    <div class="overflow-x-auto">
        <table class="data-table">
            <thead><tr><th>Code</th><th>Name</th><th>Designation</th><th>Email</th><th>Status</th><th class="text-right">Actions</th></tr></thead>
            <tbody>
            <?php if (empty($teachers)): ?>
                <tr><td colspan="6" class="py-10 text-center text-slate-400">No teachers found.</td></tr>
            <?php else: foreach ($teachers as $t): ?>
                <tr>
                    <td class="font-mono text-xs"><?= e((string) $t['employee_code']) ?></td>
                    <td class="font-medium text-slate-800"><div class="flex items-center gap-2"><?php $this->include('partials.avatar', ['avatar' => $t['photo'] ?? null, 'name' => (string) ($t['first_name'] . ' ' . $t['last_name']), 'size' => 32]); ?><span><?= e((string) ($t['first_name'] . ' ' . $t['last_name'])) ?></span></div></td>
                    <td><?= e((string) ($t['designation'] ?? '—')) ?></td>
                    <td class="text-slate-500"><?= e((string) ($t['email'] ?? '—')) ?></td>
                    <td><span class="badge <?= $t['status'] === 'active' ? 'badge-green' : 'badge-slate' ?>"><?= e(ucfirst((string) str_replace('_', ' ', $t['status']))) ?></span></td>
                    <td class="text-right">
                        <a href="<?= e(url('/admin/teachers/' . $t['id'])) ?>" class="text-slate-500 hover:text-brand-600"><i class="fa-solid fa-eye"></i></a>
                        <?php if (auth()->can('teachers.update')): ?><a href="<?= e(url('/admin/teachers/' . $t['id'] . '/edit')) ?>" class="ml-2 text-slate-500 hover:text-brand-600"><i class="fa-solid fa-pen"></i></a><?php endif; ?>
                        <?php if (auth()->can('teachers.delete')): ?>
                        <button type="button" data-delete-url="<?= e(url('/admin/teachers/' . $t['id'])) ?>" data-confirm="Delete this teacher?" class="ml-2 text-slate-500 hover:text-red-600"><i class="fa-solid fa-trash"></i></button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php $this->include('partials.pagination', ['meta' => $meta]); ?>
<form id="deleteForm" method="POST" class="hidden"><?= csrf_field() ?><?= method_field('DELETE') ?></form>
<?php $this->endSection(); ?>

<?php $this->section('page_scripts'); ?>
<script>
$(function(){ $('[data-delete-url]').on('click', function(){ if(confirm($(this).data('confirm')||'Delete?')){ const f=document.getElementById('deleteForm'); f.action=$(this).data('delete-url'); f.submit(); }}); });
</script>
<?php $this->endSection(); ?>
