<?php

use App\Http\Controllers\Admin\BranchController;
use App\Http\Controllers\Admin\CashboxSettingsController;
use App\Http\Controllers\Admin\ExpenseCategoryController;
use App\Http\Controllers\Admin\HolidayController;
use App\Http\Controllers\Admin\ImportController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EnrollmentController;
use App\Http\Controllers\Finance\CashboxController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\LessonController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\Portal\PortalController;
use App\Http\Controllers\PreferenceController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\TeacherController;
use App\Http\Controllers\TelegramController;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/home');
Route::post('locale', [PreferenceController::class, 'locale'])->name('locale.update');

Route::post('telegram/webhook/{secret}', [TelegramController::class, 'webhook'])
    ->withoutMiddleware([ValidateCsrfToken::class])
    ->name('telegram.webhook');
Route::get('tma', [TelegramController::class, 'miniApp'])->name('tma');
Route::post('tma/login', [TelegramController::class, 'miniAppLogin'])->middleware('throttle:20,1')->name('tma.login');

Route::middleware(['auth'])->group(function () {
    Route::get('home', HomeController::class)->name('home');
    Route::post('branch', [PreferenceController::class, 'branch'])->name('branch.switch');
    Route::get('portal', PortalController::class)->name('portal');

    Route::get('dashboard', DashboardController::class)->middleware('permission:dashboard.view')->name('dashboard');
    Route::get('schedule', ScheduleController::class)->middleware('permission:groups.view')->name('schedule');

    Route::middleware('permission:students.view')->group(function () {
        Route::get('students', [StudentController::class, 'index'])->name('students.index');
        Route::get('students/{student}', [StudentController::class, 'show'])->name('students.show');
    });
    Route::post('students', [StudentController::class, 'store'])->middleware('permission:students.create')->name('students.store');
    Route::patch('students/{student}', [StudentController::class, 'update'])->middleware('permission:students.update')->name('students.update');
    Route::post('students/{student}/guardians', [StudentController::class, 'storeGuardian'])->middleware('permission:students.update')->name('students.guardians.store');
    Route::delete('students/{student}/guardians/{guardian}', [StudentController::class, 'destroyGuardian'])->middleware('permission:students.update')->name('students.guardians.destroy');
    Route::post('students/{student}/guardians/{guardian}/password', [StudentController::class, 'resetGuardianPassword'])->middleware('permission:students.update')->name('students.guardians.password');

    Route::middleware('permission:enrollments.manage')->group(function () {
        Route::post('students/{student}/enrollments', [EnrollmentController::class, 'store'])->name('enrollments.store');
        Route::patch('enrollments/{enrollment}', [EnrollmentController::class, 'update'])->name('enrollments.update');
        Route::post('enrollments/{enrollment}/transfer', [EnrollmentController::class, 'transfer'])->name('enrollments.transfer');
        Route::post('enrollments/{enrollment}/leave', [EnrollmentController::class, 'leave'])->name('enrollments.leave');
        Route::post('enrollments/{enrollment}/freeze', [EnrollmentController::class, 'freeze'])->name('enrollments.freeze');
        Route::delete('enrollments/{enrollment}/freeze/{freeze}', [EnrollmentController::class, 'unfreeze'])->name('enrollments.unfreeze');
        Route::post('enrollments/{enrollment}/trial', [EnrollmentController::class, 'trialDecision'])->name('enrollments.trial');
    });
    Route::post('enrollments/{enrollment}/payments', [EnrollmentController::class, 'payment'])->middleware('permission:payments.create')->name('enrollments.payments.store');
    Route::post('enrollments/{enrollment}/refund', [EnrollmentController::class, 'refund'])->middleware('permission:refunds.create')->name('enrollments.refund');
    Route::post('enrollments/{enrollment}/transfer-balance', [EnrollmentController::class, 'transferBalance'])->middleware('permission:balance.transfer')->name('enrollments.transfer-balance');
    Route::post('enrollments/{enrollment}/write-off', [EnrollmentController::class, 'writeOff'])->middleware('permission:debts.writeoff')->name('enrollments.write-off');

    Route::post('payments/{payment}/reverse', [PaymentController::class, 'reverse'])->name('payments.reverse');
    Route::get('payments/{payment}/receipt', [PaymentController::class, 'receipt'])->middleware('permission:payments.create')->name('payments.receipt');

    Route::get('groups', [GroupController::class, 'index'])->name('groups.index');
    Route::get('groups/{group}', [GroupController::class, 'show'])->name('groups.show');
    Route::middleware('permission:groups.manage')->group(function () {
        Route::post('groups', [GroupController::class, 'store'])->name('groups.store');
        Route::put('groups/{group}', [GroupController::class, 'update'])->name('groups.update');
    });
    Route::post('groups/{group}/prices', [GroupController::class, 'storePrice'])->middleware('permission:groups.prices')->name('groups.prices.store');
    Route::post('groups/{group}/lessons', [GroupController::class, 'storeLesson'])->middleware('permission:lessons.manage')->name('groups.lessons.store');

    Route::get('lessons', [LessonController::class, 'index'])->name('attendance.index');
    Route::get('lessons/{lesson}', [LessonController::class, 'show'])->name('lessons.show');
    Route::post('lessons/{lesson}/attendance', [LessonController::class, 'attendance'])->name('lessons.attendance');
    Route::patch('lessons/{lesson}', [LessonController::class, 'update'])->middleware('permission:lessons.manage')->name('lessons.update');

    Route::get('teachers', [TeacherController::class, 'index'])->middleware('permission:teachers.view')->name('teachers.index');
    Route::get('teachers/{teacher}', [TeacherController::class, 'show'])->name('teachers.show');
    Route::post('teachers/{teacher}/payouts', [TeacherController::class, 'payout'])->middleware('permission:payouts.create')->name('teachers.payouts.store');

    Route::middleware('permission:cashboxes.view')->group(function () {
        Route::get('cashboxes', [CashboxController::class, 'index'])->name('cashboxes.index');
        Route::get('cashboxes/{cashbox}', [CashboxController::class, 'show'])->name('cashboxes.show');
    });
    Route::post('expenses', [CashboxController::class, 'expense'])->middleware('permission:expenses.create')->name('expenses.store');
    Route::post('withdrawals', [CashboxController::class, 'withdraw'])->middleware('permission:profit.withdraw')->name('withdrawals.store');
    Route::post('cashbox-transfers', [CashboxController::class, 'transfer'])->middleware('permission:cashboxes.transfer')->name('cashbox-transfers.store');

    Route::get('reports', ReportController::class)->middleware('permission:reports.view')->name('reports');

    Route::middleware('permission:leads.view')->group(function () {
        Route::get('leads', [LeadController::class, 'index'])->name('leads.index');
        Route::get('leads/{lead}', [LeadController::class, 'show'])->name('leads.show');
    });
    Route::middleware('permission:leads.manage')->group(function () {
        Route::post('leads', [LeadController::class, 'store'])->name('leads.store');
        Route::put('leads/{lead}', [LeadController::class, 'update'])->name('leads.update');
        Route::post('leads/{lead}/notes', [LeadController::class, 'note'])->name('leads.notes.store');
    });

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::middleware('permission:rooms.manage')->group(function () {
            Route::get('branches', [BranchController::class, 'index'])->name('branches.index');
            Route::post('branches/{branch}/rooms', [BranchController::class, 'storeRoom'])->name('rooms.store');
            Route::put('rooms/{room}', [BranchController::class, 'updateRoom'])->name('rooms.update');
            Route::delete('rooms/{room}', [BranchController::class, 'destroyRoom'])->name('rooms.destroy');
        });
        Route::middleware('permission:branches.manage')->group(function () {
            Route::post('branches', [BranchController::class, 'store'])->name('branches.store');
            Route::put('branches/{branch}', [BranchController::class, 'update'])->name('branches.update');
        });
        Route::middleware('permission:settings.manage')->group(function () {
            Route::post('branches/{branch}/cashboxes', [CashboxSettingsController::class, 'store'])->name('cashboxes.store');
            Route::put('cashboxes/{cashbox}', [CashboxSettingsController::class, 'update'])->name('cashboxes.update');
            Route::get('expense-categories', [ExpenseCategoryController::class, 'index'])->name('expense-categories.index');
            Route::post('expense-categories', [ExpenseCategoryController::class, 'store'])->name('expense-categories.store');
            Route::put('expense-categories/{category}', [ExpenseCategoryController::class, 'update'])->name('expense-categories.update');
        });
        Route::middleware('permission:holidays.manage')->group(function () {
            Route::get('holidays', [HolidayController::class, 'index'])->name('holidays.index');
            Route::post('holidays', [HolidayController::class, 'store'])->name('holidays.store');
            Route::delete('holidays/{holiday}', [HolidayController::class, 'destroy'])->name('holidays.destroy');
        });
        Route::middleware('permission:users.manage')->group(function () {
            Route::get('users', [UserController::class, 'index'])->name('users.index');
            Route::post('users', [UserController::class, 'store'])->name('users.store');
            Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
            Route::post('users/{user}/password', [UserController::class, 'resetPassword'])->name('users.password');
        });
        Route::middleware('permission:roles.manage')->group(function () {
            Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
            Route::put('roles/{role}', [RoleController::class, 'update'])->name('roles.update');
        });
        Route::middleware('permission:import.run')->group(function () {
            Route::get('import', [ImportController::class, 'index'])->name('import.index');
            Route::get('import/template', [ImportController::class, 'template'])->name('import.template');
            Route::post('import/preview', [ImportController::class, 'preview'])->name('import.preview');
            Route::post('import/confirm', [ImportController::class, 'confirm'])->name('import.confirm');
            Route::post('import/cancel', [ImportController::class, 'cancel'])->name('import.cancel');
        });
    });
});

require __DIR__.'/settings.php';
