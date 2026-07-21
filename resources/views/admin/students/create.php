<?php
/** @var \Core\View\View $this */
/** @var array<int,array<string,mixed>> $classes */
/** @var array<int,array<string,mixed>> $sections */
/** @var array<int,array<string,mixed>> $years */
/** @var string $admissionNo */
?>
<?php $this->extend('layouts.app'); ?>

<?php $this->section('breadcrumb'); ?><a href="<?= e(url('/admin/students')) ?>" class="hover:text-brand-600">Students</a> / Admit<?php $this->endSection(); ?>

<?php $this->section('main'); ?>
<form method="POST" action="<?= e(url('/admin/students')) ?>" enctype="multipart/form-data" class="space-y-6" data-loading>
    <?= csrf_field() ?>

    <div class="card p-6">
        <h2 class="mb-4 font-semibold text-slate-800">Personal details</h2>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div>
                <label class="form-label" for="admission_no">Admission No</label>
                <input id="admission_no" class="form-input bg-slate-50" value="<?= e($admissionNo) ?>" disabled>
            </div>
            <div>
                <label class="form-label required" for="first_name">First name</label>
                <input id="first_name" name="first_name" class="form-input" value="<?= e((string) old('first_name')) ?>" required>
            </div>
            <div>
                <label class="form-label required" for="last_name">Last name</label>
                <input id="last_name" name="last_name" class="form-input" value="<?= e((string) old('last_name')) ?>" required>
            </div>
            <div>
                <label class="form-label required" for="gender">Gender</label>
                <select id="gender" name="gender" class="form-select" required>
                    <?php foreach (['male', 'female', 'other'] as $g): ?>
                        <option value="<?= $g ?>" <?= old('gender') === $g ? 'selected' : '' ?>><?= ucfirst($g) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="form-label" for="dob">Date of birth</label>
                <input id="dob" name="dob" type="date" class="form-input" value="<?= e((string) old('dob')) ?>">
            </div>
            <div>
                <label class="form-label" for="blood_group">Blood group</label>
                <input id="blood_group" name="blood_group" class="form-input" value="<?= e((string) old('blood_group')) ?>">
            </div>
            <div>
                <label class="form-label" for="photo">Photo</label>
                <input id="photo" name="photo" type="file" accept="image/*" class="form-input">
            </div>
        </div>
    </div>

    <div class="card p-6">
        <h2 class="mb-4 font-semibold text-slate-800">Academic &amp; contact</h2>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div>
                <label class="form-label" for="class_id">Class</label>
                <select id="class_id" name="class_id" class="form-select">
                    <option value="">—</option>
                    <?php foreach ($classes as $c): ?><option value="<?= (int) $c['id'] ?>"><?= e((string) $c['name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="form-label" for="section_id">Section</label>
                <select id="section_id" name="section_id" class="form-select">
                    <option value="">—</option>
                    <?php foreach ($sections as $sec): ?><option value="<?= (int) $sec['id'] ?>"><?= e((string) ($sec['class_name'] . ' - ' . $sec['name'])) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="form-label" for="academic_year_id">Academic year</label>
                <select id="academic_year_id" name="academic_year_id" class="form-select">
                    <option value="">—</option>
                    <?php foreach ($years as $y): ?><option value="<?= (int) $y['id'] ?>"><?= e((string) $y['name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="form-label" for="roll_no">Roll no</label>
                <input id="roll_no" name="roll_no" class="form-input" value="<?= e((string) old('roll_no')) ?>">
            </div>
            <div>
                <label class="form-label" for="email">Email</label>
                <input id="email" name="email" type="email" class="form-input" value="<?= e((string) old('email')) ?>">
            </div>
            <div>
                <label class="form-label" for="phone">Phone</label>
                <input id="phone" name="phone" class="form-input" value="<?= e((string) old('phone')) ?>">
            </div>
            <div class="sm:col-span-2 lg:col-span-3">
                <label class="form-label" for="address">Address</label>
                <input id="address" name="address" class="form-input" value="<?= e((string) old('address')) ?>">
            </div>
        </div>
        <label class="mt-4 flex items-center gap-2 text-sm text-slate-600">
            <input type="checkbox" name="create_login" value="1" class="rounded border-slate-300 text-brand-600">
            Create a student login account (default password: Password123!)
        </label>
    </div>

    <div class="card p-6">
        <h2 class="mb-4 font-semibold text-slate-800">Primary guardian (optional)</h2>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <label class="form-label" for="guardian_name">Guardian name</label>
                <input id="guardian_name" name="guardian_name" class="form-input" value="<?= e((string) old('guardian_name')) ?>">
            </div>
            <div>
                <label class="form-label" for="guardian_phone">Guardian phone</label>
                <input id="guardian_phone" name="guardian_phone" class="form-input" value="<?= e((string) old('guardian_phone')) ?>">
            </div>
        </div>
    </div>

    <div class="flex justify-end gap-2">
        <a href="<?= e(url('/admin/students')) ?>" class="btn btn-secondary">Cancel</a>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> Admit student</button>
    </div>
</form>
<?php $this->endSection(); ?>
