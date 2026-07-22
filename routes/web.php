<?php

declare(strict_types=1);

/**
 * ===========================================================================
 * Web Routes
 * ---------------------------------------------------------------------------
 * Stateful, session/cookie-authenticated routes rendering HTML views.
 * The $router variable is provided by the front controller.
 * ===========================================================================
 *
 * @var \Core\Routing\Router $router
 */

use App\Controllers\Auth\LoginController;
use App\Controllers\Auth\PasswordController;
use App\Controllers\HomeController;
use App\Controllers\NotificationController;
use App\Controllers\SearchController;
use App\Controllers\Admin\DashboardController;
use App\Controllers\Admin\StudentController;
use App\Controllers\Admin\TeacherController;
use App\Controllers\Admin\TeachingAssignmentController;
use App\Controllers\Admin\StaffController;
use App\Controllers\Admin\UserController;
use App\Controllers\Admin\AcademicController;
use App\Controllers\Admin\AttendanceController;
use App\Controllers\Admin\ExamController;
use App\Controllers\Admin\FeeController;
use App\Controllers\Admin\LibraryController;
use App\Controllers\Admin\TransportController;
use App\Controllers\Admin\CommunicationController;
use App\Controllers\Admin\ReportController;
use App\Controllers\Admin\SettingController;
use App\Controllers\Admin\RoleController;
use App\Controllers\Portal\StudentPortalController;
use App\Controllers\Portal\TeacherPortalController;
use App\Controllers\Portal\ParentPortalController;

// Every web route gets security headers and CSRF protection.
$router->group(['middleware' => ['security', 'maintenance', 'csrf']], function ($router): void {

    // --- Public ------------------------------------------------------------
    $router->get('/', HomeController::class . '@index')->name('home');
    $router->get('/health', HomeController::class . '@health')->name('health');

    // --- Guest (authentication) -------------------------------------------
    $router->group(['middleware' => ['guest']], function ($router): void {
        $router->get('/login', LoginController::class . '@show')->name('login');
        $router->post('/login', LoginController::class . '@login')->middleware('throttle:10,60');
        $router->get('/forgot-password', PasswordController::class . '@showForgot')->name('password.forgot');
        $router->post('/forgot-password', PasswordController::class . '@sendReset')->middleware('throttle:5,300');
        $router->get('/reset-password/{token}', PasswordController::class . '@showReset')->name('password.reset');
        $router->post('/reset-password', PasswordController::class . '@reset');
    });

    // --- Authenticated -----------------------------------------------------
    $router->group(['middleware' => ['auth']], function ($router): void {
        $router->post('/logout', LoginController::class . '@logout')->name('logout');
        $router->get('/change-password', PasswordController::class . '@showChange')->name('password.change');
        $router->post('/change-password', PasswordController::class . '@change');

        // Notifications (all authenticated users).
        $router->get('/notifications', NotificationController::class . '@index')->name('notifications');
        $router->post('/notifications/read-all', NotificationController::class . '@readAll');
        $router->get('/notifications/{id}/read', NotificationController::class . '@read');

        // Global search (staff only).
        $router->group(['middleware' => ['role:super_admin,school_admin,principal,vice_principal,receptionist,hr,accountant,teacher,class_teacher,librarian,transport_manager']], function ($router): void {
            $router->get('/search', SearchController::class . '@index')->name('search');
        });

        // ===== Admin / Staff area =========================================
        $router->group(['prefix' => 'admin', 'middleware' => ['role:super_admin,school_admin,principal,vice_principal,receptionist,hr,accountant']], function ($router): void {
            $router->get('/dashboard', DashboardController::class . '@index')->name('admin.dashboard');
            $router->get('/dashboard/stats', DashboardController::class . '@stats')->name('admin.dashboard.stats');
            $router->get('/setup', DashboardController::class . '@setupCenter')->middleware('can:academic.view');
            $router->get('/academic/health', DashboardController::class . '@academicHealth')->middleware('can:academic.view');

            // Students
            $router->get('/students', StudentController::class . '@index')->middleware('can:students.view');
            $router->get('/students/create', StudentController::class . '@create')->middleware('can:students.create');
            $router->post('/students', StudentController::class . '@store')->middleware('can:students.create');
            $router->get('/students/{id}', StudentController::class . '@show')->middleware('can:students.view');
            $router->get('/students/{id}/edit', StudentController::class . '@edit')->middleware('can:students.update');
            $router->put('/students/{id}', StudentController::class . '@update')->middleware('can:students.update');
            $router->delete('/students/{id}', StudentController::class . '@destroy')->middleware('can:students.delete');
            $router->post('/students/promote', StudentController::class . '@promote')->middleware('can:students.update');
            // Student documents + sensitive government IDs
            $router->post('/students/{id}/documents', StudentController::class . '@uploadDocument')->middleware('can:students.documents');
            $router->get('/students/{id}/documents/{docId}/download', StudentController::class . '@downloadDocument')->middleware('can:students.view');
            $router->post('/students/{id}/documents/{docId}/review', StudentController::class . '@reviewDocument')->middleware('can:students.verify');
            $router->delete('/students/{id}/documents/{docId}', StudentController::class . '@deleteDocument')->middleware('can:students.documents');
            $router->get('/students/{id}/reveal-aadhaar', StudentController::class . '@revealAadhaar')->middleware('can:students.reveal_id');

            // Teachers
            $router->get('/teachers', TeacherController::class . '@index')->middleware('can:teachers.view');
            $router->get('/teachers/create', TeacherController::class . '@create')->middleware('can:teachers.create');
            $router->post('/teachers', TeacherController::class . '@store')->middleware('can:teachers.create');
            $router->get('/teachers/{id}', TeacherController::class . '@show')->middleware('can:teachers.view');
            $router->get('/teachers/{id}/edit', TeacherController::class . '@edit')->middleware('can:teachers.update');
            $router->put('/teachers/{id}', TeacherController::class . '@update')->middleware('can:teachers.update');
            $router->delete('/teachers/{id}', TeacherController::class . '@destroy')->middleware('can:teachers.delete');
            // Teaching assignments (year → class → section → subject → teacher)
            $router->post('/teachers/{id}/assignments', TeacherController::class . '@storeAssignment')->middleware('can:teachers.update');
            $router->post('/teachers/{id}/assignments/{assignmentId}/reassign', TeacherController::class . '@reassignAssignment')->middleware('can:teachers.update');
            $router->delete('/teachers/{id}/assignments/{assignmentId}', TeacherController::class . '@removeAssignment')->middleware('can:teachers.update');
            $router->post('/teachers/{id}/class-teacher', TeacherController::class . '@assignClassTeacher')->middleware('can:teachers.update');

            // Dedicated Teaching Assignments management module
            $router->get('/teaching-assignments', TeachingAssignmentController::class . '@index')->middleware('can:teachers.view');
            $router->post('/teaching-assignments', TeachingAssignmentController::class . '@store')->middleware('can:teachers.update');
            $router->post('/teaching-assignments/{id}/reassign', TeachingAssignmentController::class . '@reassign')->middleware('can:teachers.update');
            $router->delete('/teaching-assignments/{id}', TeachingAssignmentController::class . '@destroy')->middleware('can:teachers.update');

            // Staff
            $router->get('/staff', StaffController::class . '@index')->middleware('can:staff.view');
            $router->post('/staff', StaffController::class . '@store')->middleware('can:staff.create');
            $router->put('/staff/{id}', StaffController::class . '@update')->middleware('can:staff.update');
            $router->delete('/staff/{id}', StaffController::class . '@destroy')->middleware('can:staff.delete');

            // Academic (years, classes, sections, subjects)
            $router->get('/academic/options', AcademicController::class . '@options')->middleware('can:academic.view');
            $router->get('/academic/years', AcademicController::class . '@years')->middleware('can:academic.view');
            $router->post('/academic/years', AcademicController::class . '@storeYear')->middleware('can:academic.manage');
            $router->put('/academic/years/{id}', AcademicController::class . '@updateYear')->middleware('can:academic.manage');
            $router->post('/academic/years/{id}/activate', AcademicController::class . '@activateYear')->middleware('can:academic.manage');
            $router->post('/academic/years/{id}/archive', AcademicController::class . '@archiveYear')->middleware('can:academic.manage');
            $router->delete('/academic/years/{id}', AcademicController::class . '@deleteYear')->middleware('can:academic.manage');
            $router->post('/academic/years/copy', AcademicController::class . '@copyYearSetup')->middleware('can:academic.manage');
            $router->get('/academic/class-subjects', AcademicController::class . '@classSubjects')->middleware('can:academic.view');
            $router->post('/academic/class-subjects', AcademicController::class . '@storeClassSubject')->middleware('can:academic.manage');
            $router->delete('/academic/class-subjects/{id}', AcademicController::class . '@removeClassSubject')->middleware('can:academic.manage');
            $router->get('/academic/classes', AcademicController::class . '@classes')->middleware('can:academic.view');
            $router->post('/academic/classes', AcademicController::class . '@storeClass')->middleware('can:academic.manage');
            $router->get('/academic/classes/{id}', AcademicController::class . '@classDashboard')->middleware('can:academic.view');
            $router->put('/academic/classes/{id}', AcademicController::class . '@updateClass')->middleware('can:academic.manage');
            $router->post('/academic/classes/{id}/archive', AcademicController::class . '@archiveClass')->middleware('can:academic.manage');
            $router->delete('/academic/classes/{id}', AcademicController::class . '@deleteClass')->middleware('can:academic.manage');
            $router->get('/academic/sections', AcademicController::class . '@sections')->middleware('can:academic.view');
            $router->post('/academic/sections', AcademicController::class . '@storeSection')->middleware('can:academic.manage');
            $router->put('/academic/sections/{id}', AcademicController::class . '@updateSection')->middleware('can:academic.manage');
            $router->post('/academic/sections/{id}/archive', AcademicController::class . '@archiveSection')->middleware('can:academic.manage');
            $router->delete('/academic/sections/{id}', AcademicController::class . '@deleteSection')->middleware('can:academic.manage');
            $router->get('/academic/subjects', AcademicController::class . '@subjects')->middleware('can:academic.view');
            $router->post('/academic/subjects', AcademicController::class . '@storeSubject')->middleware('can:academic.manage');
            $router->put('/academic/subjects/{id}', AcademicController::class . '@updateSubject')->middleware('can:academic.manage');
            $router->post('/academic/subjects/{id}/archive', AcademicController::class . '@archiveSubject')->middleware('can:academic.manage');
            $router->delete('/academic/subjects/{id}', AcademicController::class . '@deleteSubject')->middleware('can:academic.manage');

            // Attendance
            $router->get('/attendance', AttendanceController::class . '@index')->middleware('can:attendance.view');
            $router->post('/attendance', AttendanceController::class . '@store')->middleware('can:attendance.mark');
            $router->get('/attendance/report', AttendanceController::class . '@report')->middleware('can:attendance.view');
            $router->post('/attendance/unlock-request', AttendanceController::class . '@requestUnlock')->middleware('can:attendance.unlock_request');
            $router->post('/attendance/unlock/{id}', AttendanceController::class . '@reviewUnlock')->middleware('can:attendance.unlock_approve');

            // Examinations
            $router->get('/exams', ExamController::class . '@index')->middleware('can:exams.view');
            $router->post('/exams', ExamController::class . '@store')->middleware('can:exams.manage');
            $router->get('/exams/{id}/marks', ExamController::class . '@marks')->middleware('can:exams.view');
            $router->post('/exams/{id}/marks', ExamController::class . '@storeMarks')->middleware('can:exams.manage');
            $router->get('/exams/{id}/report-cards', ExamController::class . '@reportCards')->middleware('can:exams.view');
            $router->post('/exams/{id}/marks/workflow', ExamController::class . '@marksWorkflow')->middleware('can:exams.view');

            // Fees
            $router->get('/fees', FeeController::class . '@index')->middleware('can:fees.view');
            $router->get('/fees/structures', FeeController::class . '@structures')->middleware('can:fees.view');
            $router->post('/fees/structures', FeeController::class . '@storeStructure')->middleware('can:fees.manage');
            $router->get('/fees/collect', FeeController::class . '@collect')->middleware('can:fees.collect');
            $router->post('/fees/collect', FeeController::class . '@storePayment')->middleware('can:fees.collect');
            $router->get('/fees/receipt/{id}', FeeController::class . '@receipt')->middleware('can:fees.view');

            // Library
            $router->get('/library', LibraryController::class . '@index')->middleware('can:library.view');
            $router->post('/library/books', LibraryController::class . '@storeBook')->middleware('can:library.manage');
            $router->post('/library/issue', LibraryController::class . '@issue')->middleware('can:library.manage');
            $router->post('/library/return', LibraryController::class . '@returnBook')->middleware('can:library.manage');

            // Transport
            $router->get('/transport', TransportController::class . '@index')->middleware('can:transport.view');
            $router->post('/transport/routes', TransportController::class . '@storeRoute')->middleware('can:transport.manage');
            $router->post('/transport/vehicles', TransportController::class . '@storeVehicle')->middleware('can:transport.manage');
            $router->post('/transport/assign', TransportController::class . '@assign')->middleware('can:transport.manage');

            // Communication
            $router->get('/communication', CommunicationController::class . '@index')->middleware('can:communication.view');
            $router->post('/communication/notices', CommunicationController::class . '@storeNotice')->middleware('can:communication.send');
            $router->post('/communication/messages', CommunicationController::class . '@sendMessage')->middleware('can:communication.send');

            // Reports
            $router->get('/reports', ReportController::class . '@index')->middleware('can:reports.view');
            $router->get('/reports/{type}', ReportController::class . '@generate')->middleware('can:reports.view');
            $router->get('/reports/{type}/export', ReportController::class . '@export')->middleware('can:reports.view');

            // Roles & Permissions (RBAC management)
            $router->get('/users', UserController::class . '@index')->middleware('can:users.view');
            $router->post('/users/{id}/toggle', UserController::class . '@toggleActive')->middleware('can:users.manage');
            $router->get('/roles', RoleController::class . '@index')->middleware('can:roles.view');
            $router->post('/roles', RoleController::class . '@store')->middleware('can:roles.manage');
            $router->put('/roles/{id}', RoleController::class . '@update')->middleware('can:roles.manage');
            $router->delete('/roles/{id}', RoleController::class . '@destroy')->middleware('can:roles.manage');

            // Settings
            $router->get('/settings', SettingController::class . '@index')->middleware('can:settings.view');
            $router->post('/settings', SettingController::class . '@update')->middleware('can:settings.manage');
            $router->post('/settings/backup', SettingController::class . '@backup')->middleware('can:settings.manage');

            // Backup manager
            $router->get('/backups', \App\Controllers\Admin\BackupController::class . '@index')->middleware('can:settings.manage');
            $router->post('/backups', \App\Controllers\Admin\BackupController::class . '@create')->middleware('can:settings.manage');
            $router->get('/backups/download', \App\Controllers\Admin\BackupController::class . '@download')->middleware('can:settings.manage');
            $router->post('/backups/restore', \App\Controllers\Admin\BackupController::class . '@restore')->middleware('can:settings.manage');
            $router->post('/backups/delete', \App\Controllers\Admin\BackupController::class . '@destroy')->middleware('can:settings.manage');

            // HR & Payroll
            $router->get('/hr', \App\Controllers\Admin\HrController::class . '@index')->middleware('can:hr.view');
            $router->post('/hr/leaves', \App\Controllers\Admin\HrController::class . '@storeLeave')->middleware('can:hr.manage');
            $router->post('/hr/leaves/{id}/status', \App\Controllers\Admin\HrController::class . '@updateLeaveStatus')->middleware('can:hr.manage');
            $router->post('/hr/payroll', \App\Controllers\Admin\HrController::class . '@generatePayroll')->middleware('can:hr.manage');
            $router->post('/hr/holidays', \App\Controllers\Admin\HrController::class . '@storeHoliday')->middleware('can:hr.manage');
        });

        // ===== Student portal =============================================
        $router->group(['prefix' => 'student', 'middleware' => ['role:student']], function ($router): void {
            $router->get('/dashboard', StudentPortalController::class . '@dashboard')->name('student.dashboard');
            $router->get('/profile', StudentPortalController::class . '@profile');
            $router->get('/attendance', StudentPortalController::class . '@attendance');
            $router->get('/results', StudentPortalController::class . '@results');
            $router->get('/fees', StudentPortalController::class . '@fees');
            $router->get('/timetable', StudentPortalController::class . '@timetable');
            $router->get('/documents', StudentPortalController::class . '@documents');
            $router->post('/documents', StudentPortalController::class . '@uploadDocument');
            $router->get('/documents/{docId}/download', StudentPortalController::class . '@downloadDocument');
        });

        // ===== Teacher portal =============================================
        $router->group(['prefix' => 'teacher', 'middleware' => ['role:teacher,class_teacher']], function ($router): void {
            $router->get('/dashboard', TeacherPortalController::class . '@dashboard')->name('teacher.dashboard');
            $router->get('/classes', TeacherPortalController::class . '@classes');
            $router->get('/attendance', TeacherPortalController::class . '@attendance')->middleware('can:attendance.mark');
            $router->post('/attendance', TeacherPortalController::class . '@markAttendance')->middleware('can:attendance.mark');
            $router->get('/subject-attendance', TeacherPortalController::class . '@subjectAttendance')->middleware('can:attendance.mark');
            $router->post('/subject-attendance', TeacherPortalController::class . '@markSubjectAttendance')->middleware('can:attendance.mark');
            $router->get('/marks', TeacherPortalController::class . '@marks')->middleware('can:exams.view');
            $router->post('/marks', TeacherPortalController::class . '@storeMarks')->middleware('can:marks.submit');
            $router->post('/marks/submit', TeacherPortalController::class . '@submitMarks')->middleware('can:marks.submit');
            $router->get('/timetable', TeacherPortalController::class . '@timetable');
        });

        // ===== Parent portal ==============================================
        $router->group(['prefix' => 'parent', 'middleware' => ['role:parent']], function ($router): void {
            $router->get('/dashboard', ParentPortalController::class . '@dashboard')->name('parent.dashboard');
            $router->get('/children', ParentPortalController::class . '@children');
            $router->get('/fees', ParentPortalController::class . '@fees');
        });
    });
});
