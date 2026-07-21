<?php
/** @var \Core\View\View $this */
/** @var array<string,mixed> $student */
/** @var array<int,array<string,mixed>> $classes */
/** @var array<int,array<string,mixed>> $sections */
$v = static fn (string $k, $d = '') => e((string) (old($k) !== '' ? old($k) : ($student[$k] ?? $d)));
?>
<?php $this->extend('layouts.app'); ?>

<?php $this->section('breadcrumb'); ?><a href="<?= e(url('/admin/students')) ?>" class="hover:text-brand-600">Students</a> / Edit<?php $this->endSection(); ?>

<?php $this->section('main'); ?>
<form method="POST" action="<?= e(url('/admin/students/' . $student['id'])) ?>" enctype="multipart/form-data" class="space-y-6" data-loading>
    <?= csrf_field() ?><?= method_field('PUT') ?>

    <div class="card p-6">
        <h2 class="mb-4 font-semibold text-slate-800">Edit student</h2>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div>
                <label class="form-label required" for="first_name">First name</label>
                <input id="first_name" name="first_name" class="form-input" value="<?= $v('first_name') ?>" required>
            </div>
            <div>
                <label class="form-label required" for="last_name">Last name</label>
                <input id="last_name" name="last_name" class="form-input" value="<?= $v('last_name') ?>" required>
            </div>
            <div>
                <label class="form-label required" for="gender">Gender</label>
                <select id="gender" name="gender" class="form-select" required>
                    <?php foreach (['male', 'female', 'other'] as $g): ?>
                        <option value="<?= $g ?>" <?= ($student['gender'] ?? '') === $g ? 'selected' : '' ?>><?= ucfirst($g) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="form-label" for="dob">Date of birth</label>
                <input id="dob" name="dob" type="date" class="form-input" value="<?= $v('dob') ?>">
            </div>
            <div>
                <label class="form-label" for="roll_no">Roll no</label>
                <input id="roll_no" name="roll_no" class="form-input" value="<?= $v('roll_no') ?>">
            </div>
            <div>
                <label class="form-label" for="blood_group">Blood group</label>
                <input id="blood_group" name="blood_group" class="form-input" value="<?= $v('blood_group') ?>">
            </div>
            <div>
                <label class="form-label" for="class_id">Class</label>
                <select id="class_id" name="class_id" class="form-select">
                    <option value="">—</option>
                    <?php foreach ($classes as $c): ?><option value="<?= (int) $c['id'] ?>" <?= (int) ($student['class_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>><?= e((string) $c['name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="form-label" for="section_id">Section</label>
                <select id="section_id" name="section_id" class="form-select">
                    <option value="">—</option>
                    <?php foreach ($sections as $sec): ?><option value="<?= (int) $sec['id'] ?>" <?= (int) ($student['section_id'] ?? 0) === (int) $sec['id'] ? 'selected' : '' ?>><?= e((string) ($sec['class_name'] . ' - ' . $sec['name'])) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="form-label required" for="status">Status</label>
                <select id="status" name="status" class="form-select" required>
                    <?php foreach (['active', 'inactive', 'graduated', 'transferred', 'alumni'] as $s): ?>
                        <option value="<?= $s ?>" <?= ($student['status'] ?? '') === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="form-label" for="email">Email</label>
                <input id="email" name="email" type="email" class="form-input" value="<?= $v('email') ?>">
            </div>
            <div>
                <label class="form-label" for="phone">Phone</label>
                <input id="phone" name="phone" class="form-input" value="<?= $v('phone') ?>">
            </div>
            <div>
                <label class="form-label" for="photo">Replace photo</label>
                <input id="photo" name="photo" type="file" accept="image/*" class="form-input">
            </div>
            <div class="sm:col-span-2 lg:col-span-3">
                <label class="form-label" for="address">Address</label>
                <input id="address" name="address" class="form-input" value="<?= $v('address') ?>">
            </div>
        </div>
    </div>

    <div class="flex justify-end gap-2">
        <a href="<?= e(url('/admin/students/' . $student['id'])) ?>" class="btn btn-secondary">Cancel</a>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> Save changes</button>
    </div>
</form>
<?php $this->endSection(); ?>
