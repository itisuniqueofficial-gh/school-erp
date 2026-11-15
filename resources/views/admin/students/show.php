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
<a href="<?= e(url('/admin/students/' . $student['id'] . '/id-card')) ?>" class="btn btn-secondary" target="_blank" rel="noopener"><i class="fa-solid fa-id-card"></i> ID Card</a>
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
                'PRN' => $student['prn'] ?? '—', 'Admission No' => $student['admission_no'] ?? '—',
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

    <!-- Identity & roll numbers -->
    <?php
    $idrBadge = static fn (string $st): string => match ($st) {
        'active' => 'badge-green', 'promoted' => 'badge-blue', 'transferred', 'left' => 'badge-amber', default => 'badge-slate',
    };
    $idrSection = static fn (array $r): string => trim((string) ($r['section_name'] ?? '')) !== '' ? (string) $r['section_name'] : '—';
    $idrCurrentYear = (int) ($student['academic_year_id'] ?? 0);
    $idrCurrent = null;
    foreach (($enrollments ?? []) as $en) {
        if ((int) $en['academic_year_id'] === $idrCurrentYear) { $idrCurrent = $en; break; }
    }
    ?>
    <section class="card idr lg:col-span-3" aria-labelledby="idr-title">
        <header class="idr-header">
            <h2 id="idr-title" class="idr-title">Identity &amp; Roll Numbers</h2>
            <p class="idr-desc">Permanent student identity and academic-year-specific roll number information.</p>
        </header>

        <!-- A. Permanent identity -->
        <div class="idr-block">
            <div class="idr-identity">
                <div class="idr-identity-main">
                    <span class="idr-label" id="idr-prn-label">Permanent Roll Number (PRN)</span>
                    <?php if (!empty($student['prn'])): ?>
                    <div class="idr-prn-row">
                        <output class="idr-prn" id="idr-prn" aria-labelledby="idr-prn-label"><?= e((string) $student['prn']) ?></output>
                        <button type="button" class="btn btn-secondary idr-copy" data-copy="<?= e((string) $student['prn']) ?>" data-idr-copy aria-label="Copy PRN <?= e((string) $student['prn']) ?>">
                            <i class="fa-regular fa-copy" aria-hidden="true"></i><span>Copy</span>
                        </button>
                        <span class="idr-copy-status" role="status" aria-live="polite"></span>
                    </div>
                    <?php else: ?>
                    <p class="idr-prn idr-prn--empty">Not issued</p>
                    <?php endif; ?>
                </div>
                <p class="idr-note"><i class="fa-solid fa-lock" aria-hidden="true"></i>
                    The PRN is permanent. It never changes with promotion, section transfer or roll-number changes, and is never reused.</p>
            </div>
        </div>

        <!-- B. Academic roll number history -->
        <div class="idr-block">
            <h3 class="idr-subtitle">Academic Roll Number History</h3>
            <?php if (empty($enrollments)): ?>
            <div class="idr-empty">
                <i class="fa-regular fa-folder-open" aria-hidden="true"></i>
                <p>No enrollment history yet.</p>
                <p class="idr-hint">A roll number is assigned automatically when the student is enrolled in a class.</p>
            </div>
            <?php else: ?>
            <div class="idr-table-wrap">
                <table class="data-table table-cards idr-table">
                    <caption class="sr-only">Academic enrollment and roll number history</caption>
                    <thead><tr>
                        <th scope="col">Academic Year</th><th scope="col">Class</th><th scope="col">Section</th>
                        <th scope="col" class="idr-num">Roll Number</th><th scope="col">Status</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($enrollments as $en): $isCur = (int) $en['academic_year_id'] === $idrCurrentYear; ?>
                        <tr<?= $isCur ? ' class="idr-row-current"' : '' ?>>
                            <td data-label="Academic Year"><?= e((string) ($en['year_name'] ?? '—')) ?><?php if ($isCur): ?> <span class="idr-tag">Current</span><?php endif; ?></td>
                            <td data-label="Class"><?= e((string) ($en['class_name'] ?? '—')) ?></td>
                            <td data-label="Section"><?= e($idrSection($en)) ?></td>
                            <td data-label="Roll Number" class="idr-num"><strong><?= e((string) $en['roll_no']) ?></strong></td>
                            <td data-label="Status"><span class="badge <?= $idrBadge((string) $en['status']) ?>"><?= e(ucfirst((string) $en['status'])) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <!-- C. Change roll number -->
        <?php if (!empty($canManageRoll) && $idrCurrent !== null): ?>
        <div class="idr-block">
            <h3 class="idr-subtitle" id="idr-change-title">Change Current Academic Year Roll Number</h3>
            <p class="idr-hint">Current roll number <strong><?= e((string) $idrCurrent['roll_no']) ?></strong>
                (<?= e(trim((string) ($idrCurrent['class_name'] ?? '') . ' ' . ($idrSection($idrCurrent) !== '—' ? $idrSection($idrCurrent) : ''))) ?>, <?= e((string) ($idrCurrent['year_name'] ?? '')) ?>).
                The new number must be unused in this class and section. Every change is recorded in the audit history.</p>
            <form method="POST" action="<?= e(url('/admin/students/' . $student['id'] . '/roll')) ?>" class="idr-form"
                  aria-labelledby="idr-change-title" data-idr-form data-no-validate data-idr-current="<?= e((string) $idrCurrent['roll_no']) ?>" data-loading>
                <?= csrf_field() ?>
                <input type="hidden" name="academic_year_id" value="<?= (int) $idrCurrentYear ?>">
                <div class="idr-field idr-field--roll">
                    <label class="form-label required" for="idr-new-roll">New Roll Number</label>
                    <input id="idr-new-roll" name="roll_no" type="text" inputmode="numeric" pattern="[0-9]{1,5}" maxlength="5"
                           class="form-input idr-control" placeholder="e.g. 12" required autocomplete="off" aria-describedby="idr-roll-help">
                    <p class="idr-field-help" id="idr-roll-help">1 to 99999</p>
                </div>
                <div class="idr-field idr-field--reason">
                    <label class="form-label required" for="idr-reason">Reason for Change</label>
                    <input id="idr-reason" name="reason" type="text" maxlength="255" class="form-input idr-control"
                           placeholder="e.g. Corrected after merit list" required autocomplete="off" aria-describedby="idr-reason-help">
                    <p class="idr-field-help" id="idr-reason-help">Required. Saved with the audit record.</p>
                </div>
                <div class="idr-field idr-field--action">
                    <span class="form-label idr-label-spacer" aria-hidden="true">&nbsp;</span>
                    <button type="submit" class="btn btn-primary idr-submit" data-confirm="Change the roll number?">
                        <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i> Change
                    </button>
                </div>
            </form>
        </div>
        <?php endif; ?>

        <!-- D. Audit history -->
        <?php if (!empty($rollHistory)): ?>
        <div class="idr-block">
            <h3 class="idr-subtitle">Roll Number Change History</h3>
            <div class="idr-table-wrap">
                <table class="data-table table-cards idr-table">
                    <caption class="sr-only">Audit history of roll number changes</caption>
                    <thead><tr>
                        <th scope="col">When</th><th scope="col">Year</th><th scope="col">Class</th><th scope="col">Change</th>
                        <th scope="col" class="idr-num">Old</th><th scope="col" class="idr-num">New</th><th scope="col">By</th><th scope="col">Reason</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($rollHistory as $h): ?>
                        <tr>
                            <td data-label="When" class="idr-nowrap"><?= e((string) $h['created_at']) ?></td>
                            <td data-label="Year"><?= e((string) ($h['year_name'] ?? '—')) ?></td>
                            <td data-label="Class"><?= e(trim((string) ($h['class_name'] ?? '') . ' ' . (string) ($h['section_name'] ?? '')) ?: '—') ?></td>
                            <td data-label="Change"><?= e(ucfirst((string) $h['change_type'])) ?></td>
                            <td data-label="Old" class="idr-num"><?= e((string) ($h['old_roll_no'] ?? '—')) ?></td>
                            <td data-label="New" class="idr-num"><?= e((string) ($h['new_roll_no'] ?? '—')) ?></td>
                            <td data-label="By"><?= e((string) ($h['changed_by_name'] ?? 'System')) ?></td>
                            <td data-label="Reason" class="idr-reason"><?= e((string) ($h['reason'] ?? '—')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </section>

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
                                    <button class="btn btn-secondary btn-sm" aria-label="Upload document"><i class="fa-solid fa-upload" aria-hidden="true"></i></button>
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
<script src="<?= e(asset('assets/js/student-identity.js')) ?>"></script>
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
