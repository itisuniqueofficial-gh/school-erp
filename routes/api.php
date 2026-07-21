<?php

declare(strict_types=1);

/**
 * ===========================================================================
 * API Routes (v1)
 * ---------------------------------------------------------------------------
 * Stateless JSON endpoints authenticated via Bearer tokens.
 *
 * @var \Core\Routing\Router $router
 * ===========================================================================
 */

use App\Controllers\Api\V1\AuthApiController;
use App\Controllers\Api\V1\StudentApiController;
use App\Controllers\Api\V1\TeacherApiController;
use App\Controllers\Api\V1\AttendanceApiController;
use App\Controllers\Api\V1\FeeApiController;
use App\Controllers\Api\V1\ResultApiController;
use App\Controllers\Api\V1\LibraryApiController;
use App\Controllers\Api\V1\ReportApiController;

$router->group(['prefix' => 'api/v1', 'middleware' => ['security', 'throttle:100,60']], function ($router): void {

    // --- Public auth endpoints --------------------------------------------
    $router->post('/auth/login', AuthApiController::class . '@login');
    $router->post('/auth/refresh', AuthApiController::class . '@refresh');

    // --- Protected endpoints ----------------------------------------------
    $router->group(['middleware' => ['api.auth']], function ($router): void {
        $router->get('/auth/me', AuthApiController::class . '@me');
        $router->post('/auth/logout', AuthApiController::class . '@logout');

        // Students
        $router->get('/students', StudentApiController::class . '@index')->middleware('can:students.view');
        $router->get('/students/{id}', StudentApiController::class . '@show')->middleware('can:students.view');
        $router->post('/students', StudentApiController::class . '@store')->middleware('can:students.create');
        $router->put('/students/{id}', StudentApiController::class . '@update')->middleware('can:students.update');
        $router->delete('/students/{id}', StudentApiController::class . '@destroy')->middleware('can:students.delete');

        // Teachers
        $router->get('/teachers', TeacherApiController::class . '@index')->middleware('can:teachers.view');
        $router->get('/teachers/{id}', TeacherApiController::class . '@show')->middleware('can:teachers.view');

        // Attendance
        $router->get('/attendance', AttendanceApiController::class . '@index')->middleware('can:attendance.view');
        $router->post('/attendance', AttendanceApiController::class . '@store')->middleware('can:attendance.mark');

        // Fees
        $router->get('/fees', FeeApiController::class . '@index')->middleware('can:fees.view');
        $router->get('/fees/student/{id}', FeeApiController::class . '@forStudent')->middleware('can:fees.view');

        // Results
        $router->get('/results/student/{id}', ResultApiController::class . '@forStudent')->middleware('can:exams.view');

        // Library
        $router->get('/library/books', LibraryApiController::class . '@books')->middleware('can:library.view');

        // Reports
        $router->get('/reports/{type}', ReportApiController::class . '@generate')->middleware('can:reports.view');
    });
});
