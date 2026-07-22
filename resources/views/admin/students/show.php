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
        <?php if (!empty($student['uid'])): ?>
        <p class="mt-1 flex items-center justify-center gap-2 text-sm">
            <span class="font-mono text-slate-700"><?= e((string) $student['uid']) ?></span>
            <button type="button" data-copy="<?= e((string) $student['uid']) ?>" class="text-brand-600 hover:text-brand-800" title="Copy Student ID" aria-label="Copy Student ID"><i class="fa-solid fa-copy"></i></button>
        </p>
        <?php endif; ?>
        <span class="badge <?= $student['status'] === 'active' ? 'badge-green' : 'badge-slate' ?> mt-2"><?= e(ucfirst((string) $student['status'])) ?></span>
        <?php if (!empty($attendance)): ?>
        <div class="mt-3 border-t border-slate-100 pt-3">
            <p class="text-xs uppercase tracking-wide text-slate-400">Attendance (<?= e((string) ($attendance['year_name'] ?? 'current')) ?>)</p>
            <?php if (!empty($attendance['hasData'])): ?>
                <p class="text-2xl font-bold text-slate-900"><?= number_format((float) $attendance['attendancePercentage'], 2) ?>%</p>
                <p class="text-xs text-slate-400"><?= (int) ($attendance['present'] + $attendance['late'] + $attendance['half_day']) ?> / <?= (int) $attendance['totalClasses'] ?> classes attended</p>
            <?php else: ?>
                <p class="text-sm text-slate-400">No records for this year.</p>
            <?php endif; ?>
        </div>
        <?php endif; ?>
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

    <!-- Government / Academic IDs -->
    <div class="card p-6">
        <h2 class="mb-4 font-semibold text-slate-800">Government &amp; Academic IDs</h2>
        <dl class="grid grid-cols-1 gap-y-3">
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Aadhaar</dt>
                <dd class="text-sm text-slate-700">
                    <span id="aadhaar-value" data-masked="<?= e($hasAadhaar ? $aadhaarMasked : '—') ?>"><?= e($hasAadhaar ? $aadhaarMasked : '—') ?></span>
                    <?php if ($hasAadhaar && $canReveal): ?>
                        <button type="button" id="reveal-aadhaar" data-url="<?= e(url('/admin/students/' . $student['id'] . '/reveal-aadhaar')) ?>" class="ml-2 text-xs text-brand-600 hover:underline"><i class="fa-solid fa-eye"></i> Show</button>
                    <?php endif; ?>
                </dd>
            </div>
            <?php foreach (['ABC ID' => 'abc_id', 'APAAR ID' => 'apaar_id', 'PEN ID' => 'pen_id', 'UDISE ID' => 'udise_id'] as $lbl => $col): ?>
            <div><dt class="text-xs font-medium uppercase tracking-wide text-slate-400"><?= e($lbl) ?></dt><dd class="text-sm text-slate-700"><?= e((string) ($student[$col] ?? '—')) ?></dd></div>
            <?php endforeach; ?>
        </dl>
    </div>

    <!-- Documents -->
    <div class="card p-6 lg:col-span-3">
        <h2 class="mb-4 font-semibold text-slate-800">Documents</h2>
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead><tr><th>Title</th><th>Type</th><th>Status</th><th class="text-right">Actions</th></tr></thead>
                <tbody>
                <?php if (empty($documents)): ?>
                    <tr><td colspan="4" class="py-6 text-center text-slate-400">No documents uploaded.</td></tr>
                <?php else: foreach ($documents as $doc):
                    $vs = (string) ($doc['verification_status'] ?? 'pending');
                    $badge = $vs === 'verified' ? 'badge-green' : ($vs === 'rejected' ? 'badge-red' : 'badge-slate'); ?>
                    <tr>
                        <td class="font-medium text-slate-800"><?= e((string) $doc['title']) ?></td>
                        <td><?= e((string) ($documentTypes[$doc['type']] ?? $doc['type'] ?? '—')) ?></td>
                        <td><span class="badge <?= $badge ?>"><?= e(ucfirst($vs)) ?></span></td>
                        <td class="text-right">
                            <a href="<?= e(url('/admin/students/' . $student['id'] . '/documents/' . $doc['id'] . '/download')) ?>" target="_blank" class="text-brand-600 hover:underline" aria-label="View document"><i class="fa-solid fa-eye"></i></a>
                            <?php if (!empty($canVerify) && $vs !== 'verified'): ?>
                            <form method="POST" action="<?= e(url('/admin/students/' . $student['id'] . '/documents/' . $doc['id'] . '/review')) ?>" class="inline"><?= csrf_field() ?><input type="hidden" name="decision" value="approve"><button class="ml-2 text-green-600 hover:underline" aria-label="Verify"><i class="fa-solid fa-check"></i></button></form>
                            <?php endif; ?>
                            <?php if (!empty($canVerify) && $vs !== 'rejected'): ?>
                            <form method="POST" action="<?= e(url('/admin/students/' . $student['id'] . '/documents/' . $doc['id'] . '/review')) ?>" class="inline"><?= csrf_field() ?><input type="hidden" name="decision" value="reject"><button class="ml-2 text-amber-600 hover:underline" aria-label="Reject"><i class="fa-solid fa-xmark"></i></button></form>
                            <?php endif; ?>
                            <?php if (!empty($canDocs)): ?>
                            <form method="POST" action="<?= e(url('/admin/students/' . $student['id'] . '/documents/' . $doc['id'])) ?>" class="inline"><?= csrf_field() ?><?= method_field('DELETE') ?><button class="ml-2 text-red-600 hover:underline" data-confirm="Delete this document?" aria-label="Delete"><i class="fa-solid fa-trash"></i></button></form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <?php if (!empty($canDocs)): ?>
        <form method="POST" action="<?= e(url('/admin/students/' . $student['id'] . '/documents')) ?>" enctype="multipart/form-data" class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-4" data-loading>
            <?= csrf_field() ?>
            <input type="text" name="title" placeholder="Title (optional)" class="form-input">
            <select name="type" class="form-select" required><?php foreach ($documentTypes as $k => $label): ?><option value="<?= e($k) ?>"><?= e($label) ?></option><?php endforeach; ?></select>
            <input type="file" name="document" class="form-input" accept=".pdf,.jpg,.jpeg,.png,.webp" required>
            <button class="btn btn-primary justify-center"><i class="fa-solid fa-upload"></i> Upload</button>
        </form>
        <p class="mt-2 text-xs text-slate-400">Allowed: PDF, JPG, PNG, WEBP. Files are stored securely outside the web root and served only to authorized users.</p>
        <?php endif; ?>
    </div>
</div>
<?php $this->endSection(); ?>

<?php $this->section('page_scripts'); ?>
<script>
$(function(){
  $('#reveal-aadhaar').on('click', function(){
    var $btn = $(this), $val = $('#aadhaar-value');
    if ($btn.data('shown')) { $val.text($val.data('masked')); $btn.data('shown', false).html('<i class="fa-solid fa-eye"></i> Show'); return; }
    $.getJSON($btn.data('url')).done(function(r){
      $val.text(r.aadhaar || '—'); $btn.data('shown', true).html('<i class="fa-solid fa-eye-slash"></i> Hide');
    }).fail(function(){ if(window.toast) toast('Not authorized to reveal.', 'error'); });
  });
});
</script>
<?php $this->endSection(); ?>
