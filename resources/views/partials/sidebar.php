<?php
/** @var \Core\View\View $this */
/** @var array<string,mixed> $user */
$path = request()->path();

/** Render a nav link; active state derived from the current path. */
$link = static function (string $href, string $icon, string $label) use ($path): string {
    $active = $href === '/admin/dashboard'
        ? $path === $href
        : str_starts_with($path, $href);
    $classes = $active
        ? 'bg-brand-50 text-brand-700'
        : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900';
    return '<a href="' . e(url($href)) . '" class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition ' . $classes . '">'
        . '<i class="fa-solid ' . e($icon) . ' w-5 text-center"></i><span>' . e($label) . '</span></a>';
};

$roles = $user['roles'] ?? [];
$isStaff = (bool) array_intersect($roles, ['super_admin', 'school_admin', 'principal', 'vice_principal', 'receptionist', 'hr', 'accountant', 'librarian', 'transport_manager']);
?>
<div class="flex h-16 items-center gap-2 border-b border-slate-200 px-5">
    <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-brand-600 text-white">
        <i class="fa-solid fa-graduation-cap"></i>
    </span>
    <span class="truncate text-lg font-bold text-slate-900"><?= e($appName ?? config('app.name')) ?></span>
</div>

<nav class="space-y-6 px-3 py-5">
    <div class="space-y-1">
        <?php if ($isStaff): ?>
            <?= $link('/admin/dashboard', 'fa-gauge-high', 'Dashboard') ?>
            <?= $link('/admin/lookup', 'fa-magnifying-glass-arrow-right', 'Go to Record by ID') ?>
        <?php endif; ?>
        <?php if (auth()->hasRole('student')): ?><?= $link('/student/dashboard', 'fa-gauge-high', 'Dashboard') ?><?php endif; ?>
        <?php if (auth()->hasAnyRole(['teacher', 'class_teacher'])): ?><?= $link('/teacher/dashboard', 'fa-gauge-high', 'Dashboard') ?><?php endif; ?>
        <?php if (auth()->hasRole('parent')): ?><?= $link('/parent/dashboard', 'fa-gauge-high', 'Dashboard') ?><?php endif; ?>
    </div>

    <?php if ($isStaff): ?>
    <div>
        <p class="px-3 pb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">People</p>
        <div class="space-y-1">
            <?php if (auth()->can('students.view')): ?><?= $link('/admin/students', 'fa-user-graduate', 'Students') ?><?php endif; ?>
            <?php if (auth()->can('students.update')): ?><?= $link('/admin/promotions', 'fa-arrow-up-right-dots', 'Promotion & Rollover') ?><?php endif; ?>
            <?php if (auth()->can('teachers.view')): ?><?= $link('/admin/teachers', 'fa-chalkboard-user', 'Teachers') ?><?php endif; ?>
            <?php if (auth()->can('teachers.view')): ?><?= $link('/admin/teaching-assignments', 'fa-diagram-project', 'Teaching Assignments') ?><?php endif; ?>
            <?php if (auth()->can('staff.view')): ?><?= $link('/admin/staff', 'fa-users', 'Staff') ?><?php endif; ?>
        </div>
    </div>

    <div>
        <p class="px-3 pb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Academics</p>
        <div class="space-y-1">
            <?php if (auth()->can('academic.view')): ?><?= $link('/admin/setup', 'fa-list-check', 'Setup Center') ?><?php endif; ?>
            <?php if (auth()->can('academic.view')): ?><?= $link('/admin/academic/classes', 'fa-school', 'Academic') ?><?php endif; ?>
            <?php if (auth()->can('attendance.view')): ?><?= $link('/admin/attendance', 'fa-clipboard-check', 'Attendance') ?><?php endif; ?>
            <?php if (auth()->can('exams.view')): ?><?= $link('/admin/exams', 'fa-award', 'Examinations') ?><?php endif; ?>
            <?php if (auth()->can('exams.view')): ?><?= $link('/admin/hall-tickets', 'fa-ticket', 'Hall Tickets') ?><?php endif; ?>
            <?php if (auth()->can('certificates.view')): ?><?= $link('/admin/certificates', 'fa-certificate', 'Certificates') ?><?php endif; ?>
            <?php if (auth()->can('certificates.view')): ?><?= $link('/admin/certificate-requests', 'fa-inbox', 'Certificate Requests') ?><?php endif; ?>
        </div>
    </div>

    <div>
        <p class="px-3 pb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Operations</p>
        <div class="space-y-1">
            <?php $erp = new \App\Services\InstitutionService(); ?>
            <?php if (auth()->can('fees.view') && $erp->moduleEnabled('fees')): ?><?= $link('/admin/fees', 'fa-file-invoice-dollar', 'Fees') ?><?php endif; ?>
            <?php if (auth()->can('library.view') && $erp->moduleEnabled('library')): ?><?= $link('/admin/library', 'fa-book', 'Library') ?><?php endif; ?>
            <?php if (auth()->can('transport.view') && $erp->moduleEnabled('transport')): ?><?= $link('/admin/transport', 'fa-bus', 'Transport') ?><?php endif; ?>
            <?php if (auth()->can('communication.view') && $erp->moduleEnabled('communication')): ?><?= $link('/admin/communication', 'fa-bullhorn', 'Communication') ?><?php endif; ?>
        </div>
    </div>

    <div>
        <p class="px-3 pb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">System</p>
        <div class="space-y-1">
            <?php if (auth()->can('reports.view')): ?><?= $link('/admin/reports', 'fa-chart-line', 'Reports') ?><?php endif; ?>
            <?php if (auth()->can('users.view')): ?><?= $link('/admin/users', 'fa-users-gear', 'User Management') ?><?php endif; ?>
            <?php if (auth()->can('hr.view') && $erp->moduleEnabled('hr')): ?><?= $link('/admin/hr', 'fa-briefcase', 'HR & Payroll') ?><?php endif; ?>
            <?php if (auth()->can('roles.view')): ?><?= $link('/admin/roles', 'fa-user-shield', 'Roles & Access') ?><?php endif; ?>
            <?php if (auth()->can('settings.view')): ?><?= $link('/admin/audit', 'fa-clock-rotate-left', 'Audit Log') ?><?php endif; ?>
            <?php if (auth()->can('settings.view')): ?><?= $link('/admin/settings', 'fa-gear', 'Settings') ?><?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if (auth()->hasRole('student')): ?>
    <div class="space-y-1">
        <?= $link('/student/profile', 'fa-id-card', 'My Profile') ?>
        <?= $link('/student/attendance', 'fa-clipboard-check', 'Attendance') ?>
        <?= $link('/student/results', 'fa-award', 'Results') ?>
        <?= $link('/student/report-cards', 'fa-file-lines', 'Report Cards') ?>
        <?= $link('/student/hall-tickets', 'fa-ticket', 'Hall Tickets') ?>
        <?= $link('/student/certificates', 'fa-certificate', 'Documents & Certificates') ?>
        <?= $link('/student/documents', 'fa-folder-open', 'My Documents') ?>
        <?= $link('/student/fees', 'fa-file-invoice-dollar', 'Fees') ?>
        <?= $link('/student/timetable', 'fa-calendar-days', 'Timetable') ?>
    </div>
    <?php endif; ?>

    <?php if (auth()->hasAnyRole(['teacher', 'class_teacher'])): ?>
    <div class="space-y-1">
        <?= $link('/teacher/classes', 'fa-chalkboard', 'My Classes') ?>
        <?= $link('/teacher/attendance', 'fa-clipboard-check', 'Attendance') ?>
        <?= $link('/teacher/subject-attendance', 'fa-clipboard-list', 'Subject Attendance') ?>
        <?= $link('/teacher/marks', 'fa-pen-to-square', 'Marks Entry') ?>
        <?= $link('/teacher/timetable', 'fa-calendar-days', 'Timetable') ?>
    </div>
    <?php endif; ?>

    <?php if (auth()->hasRole('parent')): ?>
    <div class="space-y-1">
        <?= $link('/parent/children', 'fa-children', 'My Children') ?>
        <?= $link('/parent/report-cards', 'fa-file-lines', 'Report Cards') ?>
        <?= $link('/parent/fees', 'fa-file-invoice-dollar', 'Fees') ?>
    </div>
    <?php endif; ?>
</nav>
