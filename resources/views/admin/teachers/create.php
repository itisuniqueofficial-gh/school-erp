<?php /** @var \Core\View\View $this */ /** @var string $code */ ?>
<?php $this->extend('layouts.app'); ?>
<?php $this->section('breadcrumb'); ?><a href="<?= e(url('/admin/teachers')) ?>" class="hover:text-brand-600">Teachers</a> / Add<?php $this->endSection(); ?>
<?php $this->section('main'); ?>
<form method="POST" action="<?= e(url('/admin/teachers')) ?>" class="space-y-6" data-loading>
    <?= csrf_field() ?>
    <div class="card p-6">
        <h2 class="mb-4 font-semibold text-slate-800">Teacher details</h2>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div><label class="form-label">Employee Code</label><input class="form-input bg-slate-50" value="<?= e($code) ?>" disabled></div>
            <div><label class="form-label required" for="first_name">First name</label><input id="first_name" name="first_name" class="form-input" value="<?= e((string) old('first_name')) ?>" required></div>
            <div><label class="form-label required" for="last_name">Last name</label><input id="last_name" name="last_name" class="form-input" value="<?= e((string) old('last_name')) ?>" required></div>
            <div><label class="form-label" for="gender">Gender</label><select id="gender" name="gender" class="form-select"><option value="">—</option><?php foreach(['male','female','other'] as $g):?><option value="<?=$g?>"><?=ucfirst($g)?></option><?php endforeach;?></select></div>
            <div><label class="form-label" for="designation">Designation</label><input id="designation" name="designation" class="form-input" value="<?= e((string) old('designation')) ?>"></div>
            <div><label class="form-label" for="email">Email</label><input id="email" name="email" type="email" class="form-input" value="<?= e((string) old('email')) ?>"></div>
            <div><label class="form-label" for="phone">Phone</label><input id="phone" name="phone" class="form-input" value="<?= e((string) old('phone')) ?>"></div>
            <div><label class="form-label" for="qualification">Qualification</label><input id="qualification" name="qualification" class="form-input" value="<?= e((string) old('qualification')) ?>"></div>
            <div><label class="form-label" for="experience_years">Experience (years)</label><input id="experience_years" name="experience_years" type="number" step="0.5" class="form-input" value="<?= e((string) old('experience_years')) ?>"></div>
            <div><label class="form-label" for="basic_salary">Basic salary</label><input id="basic_salary" name="basic_salary" type="number" step="0.01" class="form-input" value="<?= e((string) old('basic_salary')) ?>"></div>
        </div>
        <label class="mt-4 flex items-center gap-2 text-sm text-slate-600"><input type="checkbox" name="create_login" value="1" class="rounded border-slate-300 text-brand-600"> Create teacher login (password: Password123!)</label>
    </div>
    <div class="flex justify-end gap-2">
        <a href="<?= e(url('/admin/teachers')) ?>" class="btn btn-secondary">Cancel</a>
        <button class="btn btn-primary"><i class="fa-solid fa-check"></i> Save teacher</button>
    </div>
</form>
<?php $this->endSection(); ?>
