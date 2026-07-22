<?php
/** @var \Core\View\View $this */
/** @var array<int,array<string,mixed>> $students */
/** @var array<string,string> $types */
?>
<?php $this->extend('layouts.app'); ?>
<?php $this->section('breadcrumb'); ?><a href="<?= e(url('/admin/certificates')) ?>" class="hover:text-brand-600">Certificates</a> / Issue<?php $this->endSection(); ?>
<?php $this->section('main'); ?>
<div class="card max-w-2xl">
    <form method="POST" action="<?= e(url('/admin/certificates')) ?>" class="space-y-4 p-6">
        <?= csrf_field() ?>
        <div>
            <label class="block text-sm font-semibold text-slate-700">Student <span class="text-red-500">*</span></label>
            <select name="student_id" required class="form-input">
                <option value="">— Select a student —</option>
                <?php foreach ($students as $s): ?>
                    <option value="<?= (int) $s['id'] ?>"><?= e(trim((string) ($s['first_name'] . ' ' . $s['last_name']))) ?> · <?= e((string) $s['admission_no']) ?><?= $s['class_name'] ? ' · ' . e((string) $s['class_name']) : '' ?></option>
                <?php endforeach; ?>
            </select>
            <p class="mt-1 text-xs text-slate-400">Student name, class, admission number and parent details are filled in automatically.</p>
        </div>
        <div>
            <label class="block text-sm font-semibold text-slate-700">Certificate Type <span class="text-red-500">*</span></label>
            <select name="type" required class="form-input" onchange="document.getElementById('customBody').style.display = this.value === 'custom' ? 'block' : 'none';">
                <?php foreach ($types as $k => $label): ?>
                    <option value="<?= e($k) ?>"><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <label class="block text-sm font-semibold text-slate-700">Reference No <span class="text-slate-400">(optional)</span></label>
                <input type="text" name="reference_no" class="form-input" placeholder="e.g. TC/2026/014">
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700">Issue Date <span class="text-slate-400">(optional)</span></label>
                <input type="date" name="issued_on" class="form-input">
            </div>
        </div>
        <div id="customBody" style="display:none;">
            <label class="block text-sm font-semibold text-slate-700">Custom Body</label>
            <textarea name="body" rows="4" class="form-input" placeholder="Required for custom certificates. You may use placeholders like {name}, {class}, {admission_no}, {year}, {school}."></textarea>
            <p class="mt-1 text-xs text-slate-400">Placeholders such as <code>{name}</code>, <code>{class}</code>, <code>{admission_no}</code>, <code>{year}</code> are replaced automatically.</p>
        </div>
        <div class="flex gap-2 pt-2">
            <button class="btn btn-primary"><i class="fa-solid fa-certificate"></i> Issue Certificate</button>
            <a href="<?= e(url('/admin/certificates')) ?>" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>
<?php $this->endSection(); ?>
