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
            <img src="<?= e(url('/media/avatar/' . pathinfo((string) $student['photo'], PATHINFO_FILENAME))) ?>" alt="<?= e($name) ?>" class="mx-auto h-28 w-28 rounded-full object-cover" onerror="this.style.display='none'">
        <?php else: ?>
            <span class="mx-auto flex h-28 w-28 items-center justify-center rounded-full bg-brand-600 text-3xl font-bold text-white"><?= e(strtoupper(substr((string) $student['first_name'], 0, 1))) ?></span>
        <?php endif; ?>
        <?php if (auth()->can('students.update')): ?>
        <div class="mt-3">
            <form method="POST" action="<?= e(url('/admin/students/' . $student['id'] . '/photo')) ?>" enctype="multipart/form-data" class="flex flex-col items-center gap-2" data-loading>
                <?= csrf_field() ?>
                <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" required class="text-xs file:mr-2 file:rounded-md file:border-0 file:bg-brand-50 file:px-3 file:py-1.5 file:text-brand-700">
                <button class="btn btn-primary btn-sm"><i class="fa-solid fa-upload"></i> <?= !empty($student['photo']) ? 'Change' : 'Upload' ?> Photo</button>
            </form>
            <?php if (!empty($student['photo'])): ?>
            <form method="POST" action="<?= e(url('/admin/students/' . $student['id'] . '/photo')) ?>" class="mt-2" data-confirm="Remove this student's profile photo?">
                <?= csrf_field() ?><?= method_field('DELETE') ?>
                <button class="text-xs font-medium text-red-600 hover:underline"><i class="fa-solid fa-trash"></i> Remove photo</button>
            </form>
            <?php endif; ?>
            <p class="mt-1 text-xs text-slate-400">JPG, PNG or WebP &middot; max 1 MB &middot; square is best</p>
        </div>
        <?php endif; ?>
        <h2 class="mt-4 text-lg font-bold text-slate-900"><?= e($name) ?></h2>
        <p class="text-sm text-slate-500"><?= e((string) $student['admission_no']) ?></p>
        <?php if (!empty($student['uid'])): ?>
        <p class="mt-1 flex items-center justify-center gap-2 text-sm">
            <span class="font-mono text-slate-700"><?= e((string) $student['uid']) ?></span>
            <button type="button" data-copy="<?= e((string) $student['uid']) ?>" class="text-brand-600 hover:text-brand-800" title="Copy Student ID" aria-label="Copy Student ID"><i class="fa-solid fa-copy"></i></button>
        </p>
        <div class="mt-3 flex justify-center">
            <div data-qr="<?= e((string) $student['uid']) ?>" data-qr-size="96" title="Student ID QR" aria-label="QR code for student ID <?= e((string) $student['uid']) ?>"></div>
        </div>
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

    <!-- Personal File checklist -->
    <?php
    $pfSum = $personalFileSummary ?? ['percent' => 0, 'applicable' => 0, 'verified' => 0, 'pending' => 0, 'rejected' => 0, 'missing' => 0, 'not_applicable' => 0, 'complete' => false];
    $pfBadge = [
        'verified' => 'badge-green', 'pending' => 'badge-slate', 'rejected' => 'badge-red',
        'missing' => 'badge-amber', 'not_applicable' => 'badge-slate',
    ];
    $pfLabel = [
        'verified' => 'Verified', 'pending' => 'Under verification', 'rejected' => 'Rejected',
        'missing' => 'Missing', 'not_applicable' => 'Not applicable',
    ];
    ?>
    <div class="card p-6 lg:col-span-3">
        <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <h2 class="font-semibold text-slate-800"><i class="fa-solid fa-folder-open mr-1 text-brand-600"></i> Personal File</h2>
            <div class="flex items-center gap-3">
                <a href="<?= e(url('/admin/students/' . $student['id'] . '/personal-file/pdf')) ?>" target="_blank" class="btn btn-secondary btn-sm"><i class="fa-solid fa-print"></i> Print Checklist</a>
                <div class="text-sm text-slate-500">
                <?= (int) $pfSum['verified'] ?>/<?= (int) $pfSum['applicable'] ?> required verified
                &middot; <?= (int) $pfSum['pending'] ?> pending &middot; <?= (int) $pfSum['missing'] ?> missing
                <?php if ((int) $pfSum['rejected'] > 0): ?>&middot; <span class="text-red-600"><?= (int) $pfSum['rejected'] ?> rejected</span><?php endif; ?>
                </div>
            </div>
        </div>
        <?php if (!$pfSum['complete']): ?>
            <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">
                <i class="fa-solid fa-triangle-exclamation mr-1"></i> Personal File incomplete — <?= (int) $pfSum['applicable'] - (int) $pfSum['verified'] ?> required document(s) pending.
            </div>
        <?php endif; ?>
        <div class="mb-4 h-2 w-full overflow-hidden rounded-full bg-slate-100">
            <div class="h-full rounded-full bg-brand-500" style="width: <?= (int) $pfSum['percent'] ?>%"></div>
        </div>
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead><tr><th>Document</th><th>Status</th><th class="text-right">Action</th></tr></thead>
                <tbody>
                <?php foreach (($personalFile ?? []) as $item): $st = (string) $item['status']; $doc = $item['document']; ?>
                    <tr>
                        <td class="font-medium text-slate-800">
                            <?= e((string) $item['name']) ?>
                            <?php if (!$item['is_required']): ?><span class="ml-1 text-xs text-slate-400">(optional)</span><?php endif; ?>
                            <?php if ($st === 'not_applicable' && !empty($item['na_reason'])): ?><div class="text-xs text-slate-400">N/A: <?= e((string) $item['na_reason']) ?></div><?php endif; ?>
                        </td>
                        <td><span class="badge <?= $pfBadge[$st] ?? 'badge-slate' ?>"><?= e($pfLabel[$st] ?? ucfirst($st)) ?></span></td>
                        <td class="text-right">
                            <?php if ($doc !== null): ?>
                                <a href="<?= e(url('/admin/students/' . $student['id'] . '/documents/' . $doc['id'] . '/download')) ?>" target="_blank" class="text-brand-600 hover:underline" aria-label="View"><i class="fa-solid fa-eye"></i></a>
                                <?php if (!empty($canVerify) && $st !== 'verified'): ?>
                                <form method="POST" action="<?= e(url('/admin/students/' . $student['id'] . '/documents/' . $doc['id'] . '/review')) ?>" class="inline"><?= csrf_field() ?><input type="hidden" name="decision" value="approve"><button class="ml-2 text-green-600 hover:underline" aria-label="Verify"><i class="fa-solid fa-check"></i></button></form>
                                <?php endif; ?>
                                <?php if (!empty($canVerify) && $st !== 'rejected'): ?>
                                <form method="POST" action="<?= e(url('/admin/students/' . $student['id'] . '/documents/' . $doc['id'] . '/review')) ?>" class="inline"><?= csrf_field() ?><input type="hidden" name="decision" value="reject"><button class="ml-2 text-amber-600 hover:underline" aria-label="Reject"><i class="fa-solid fa-xmark"></i></button></form>
                                <?php endif; ?>
                            <?php elseif (!empty($canDocs) && $st !== 'not_applicable'): ?>
                                <form method="POST" action="<?= e(url('/admin/students/' . $student['id'] . '/documents')) ?>" enctype="multipart/form-data" class="inline-flex items-center gap-1" data-loading>
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="type" value="<?= e((string) $item['code']) ?>">
                                    <input type="hidden" name="title" value="<?= e((string) $item['name']) ?>">
                                    <input type="file" name="document" class="form-input text-xs" accept=".pdf,.jpg,.jpeg,.png,.webp" required>
                                    <button class="btn btn-secondary btn-sm"><i class="fa-solid fa-upload"></i></button>
                                </form>
                            <?php endif; ?>
                            <?php if (!empty($canDocs)): ?>
                                <?php if ($st === 'not_applicable'): ?>
                                <form method="POST" action="<?= e(url('/admin/students/' . $student['id'] . '/documents/na/clear')) ?>" class="inline"><?= csrf_field() ?><input type="hidden" name="code" value="<?= e((string) $item['code']) ?>"><button class="ml-2 text-xs text-slate-500 hover:underline">Restore</button></form>
                                <?php elseif (!$item['is_required']): ?>
                                <form method="POST" action="<?= e(url('/admin/students/' . $student['id'] . '/documents/na')) ?>" class="inline"><?= csrf_field() ?><input type="hidden" name="code" value="<?= e((string) $item['code']) ?>"><input type="hidden" name="reason" value="Not applicable"><button class="ml-2 text-xs text-slate-500 hover:underline" data-confirm="Mark this document as not applicable for this student?">N/A</button></form>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
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
