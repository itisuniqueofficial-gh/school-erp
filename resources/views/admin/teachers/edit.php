<?php /** @var \Core\View\View $this */ /** @var array<string,mixed> $teacher */ ?>
<?php $this->extend('layouts.app'); ?>
<?php $this->section('breadcrumb'); ?><a href="<?= e(url('/admin/teachers')) ?>" class="hover:text-brand-600">Teachers</a> / Edit<?php $this->endSection(); ?>
<?php $this->section('main'); ?>
<form method="POST" action="<?= e(url('/admin/teachers/' . $teacher['id'])) ?>" class="space-y-6" data-loading>
    <?= csrf_field() ?><?= method_field('PUT') ?>
    <div class="card p-6">
        <h2 class="mb-4 font-semibold text-slate-800">Edit teacher</h2>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div><label class="form-label required" for="first_name">First name</label><input id="first_name" name="first_name" class="form-input" value="<?= e((string) $teacher['first_name']) ?>" required></div>
            <div><label class="form-label required" for="last_name">Last name</label><input id="last_name" name="last_name" class="form-input" value="<?= e((string) $teacher['last_name']) ?>" required></div>
            <div><label class="form-label" for="designation">Designation</label><input id="designation" name="designation" class="form-input" value="<?= e((string) ($teacher['designation'] ?? '')) ?>"></div>
            <div><label class="form-label" for="email">Email</label><input id="email" name="email" type="email" class="form-input" value="<?= e((string) ($teacher['email'] ?? '')) ?>"></div>
            <div><label class="form-label" for="phone">Phone</label><input id="phone" name="phone" class="form-input" value="<?= e((string) ($teacher['phone'] ?? '')) ?>"></div>
            <div><label class="form-label" for="qualification">Qualification</label><input id="qualification" name="qualification" class="form-input" value="<?= e((string) ($teacher['qualification'] ?? '')) ?>"></div>
            <div><label class="form-label" for="experience_years">Experience (years)</label><input id="experience_years" name="experience_years" type="number" step="0.5" class="form-input" value="<?= e((string) ($teacher['experience_years'] ?? '')) ?>"></div>
            <div><label class="form-label" for="basic_salary">Basic salary</label><input id="basic_salary" name="basic_salary" type="number" step="0.01" class="form-input" value="<?= e((string) ($teacher['basic_salary'] ?? '')) ?>"></div>
            <div><label class="form-label required" for="status">Status</label><select id="status" name="status" class="form-select" required><?php foreach(['active','inactive','on_leave','resigned'] as $s):?><option value="<?=$s?>" <?= ($teacher['status']??'')===$s?'selected':''?>><?=ucfirst(str_replace('_',' ',$s))?></option><?php endforeach;?></select></div>
        </div>
    </div>
    <div class="flex justify-end gap-2">
        <a href="<?= e(url('/admin/teachers/' . $teacher['id'])) ?>" class="btn btn-secondary">Cancel</a>
        <button class="btn btn-primary"><i class="fa-solid fa-check"></i> Save changes</button>
    </div>
</form>
<?php $this->endSection(); ?>
