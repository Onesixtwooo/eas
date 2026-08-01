<?php

use App\Http\Controllers\Admin\InstructorAssignmentController;
use App\Http\Controllers\Admin\InstructorController;
use App\Http\Controllers\Admin\StudentController as AdminStudentController;
use App\Http\Controllers\Admin\SubjectController;
use App\Http\Controllers\Admin\UserAccountController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExcuseRequestController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RegistrationController;
use App\Http\Controllers\VerificationController;
use App\Http\Controllers\WorkflowController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => auth()->check() ? redirect()->route('dashboard') : view('auth.login'));
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'login'])->name('login');
    Route::post('/login', [AuthController::class, 'authenticate'])->name('login.attempt');
    Route::get('/register', [RegistrationController::class, 'create'])->name('register');
    Route::post('/register', [RegistrationController::class, 'store'])->name('register.store');
    Route::get('/forgot-password', [AuthController::class, 'forgot'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'emailReset'])->name('password.email');
});
Route::get('/verify/{reference}', [VerificationController::class, 'show'])->name('verify');
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/requests', [ExcuseRequestController::class, 'index'])->name('requests.index');
    Route::middleware('role:student')->group(function () {
        Route::get('/requests/create', [ExcuseRequestController::class, 'create'])->name('requests.create');
        Route::post('/requests', [ExcuseRequestController::class, 'store'])->name('requests.store');
        Route::post('/requests/{excuseRequest}/submit', [ExcuseRequestController::class, 'submit'])->name('requests.submit');
    });
    Route::get('/requests/{excuseRequest}', [ExcuseRequestController::class, 'show'])->name('requests.show');
    Route::get('/requests/{excuseRequest}/slip', [ExcuseRequestController::class, 'slip'])->name('requests.slip');
    Route::post('/requests/{excuseRequest}/review', [WorkflowController::class, 'review'])->middleware('role:admin,program_head')->name('requests.review');
    Route::post('/requests/{excuseRequest}/acknowledge', [WorkflowController::class, 'acknowledge'])->middleware('role:faculty')->name('requests.acknowledge');
    Route::post('/requests/{excuseRequest}/complete', [WorkflowController::class, 'complete'])->middleware('role:faculty')->name('requests.complete');
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile');
    Route::put('/profile', [ProfileController::class, 'updateDetails'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password.update');
    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', fn () => redirect()->route('admin.students.index'))->name('index');
        Route::get('/students', [AdminStudentController::class, 'index'])->name('students.index');
        Route::get('/accounts', [UserAccountController::class, 'index'])->name('accounts.index');
        Route::get('/accounts/create', [UserAccountController::class, 'create'])->name('accounts.create');
        Route::post('/accounts', [UserAccountController::class, 'store'])->name('accounts.store');
        Route::get('/accounts/{account}/edit', [UserAccountController::class, 'edit'])->name('accounts.edit');
        Route::put('/accounts/{account}', [UserAccountController::class, 'update'])->name('accounts.update');
        Route::delete('/accounts/{account}', [UserAccountController::class, 'destroy'])->name('accounts.destroy');
        Route::get('/students/create', [AdminStudentController::class, 'create'])->name('students.create');
        Route::post('/students', [AdminStudentController::class, 'store'])->name('students.store');
        Route::delete('/students/bulk', [AdminStudentController::class, 'bulkDestroy'])->name('students.bulk-destroy');
        Route::get('/students/{student}', [AdminStudentController::class, 'show'])->name('students.show');
        Route::get('/students/{student}/assessment-form', [AdminStudentController::class, 'assessmentForm'])->name('students.assessment-form');
        Route::patch('/students/{student}/status', [AdminStudentController::class, 'toggleStatus'])->name('students.status');
        Route::delete('/students/{student}', [AdminStudentController::class, 'destroy'])->name('students.destroy');
        Route::get('/instructors/create', [InstructorController::class, 'create'])->name('instructors.create');
        Route::post('/instructors', [InstructorController::class, 'store'])->name('instructors.store');
        Route::get('/subjects', [SubjectController::class, 'index'])->name('subjects.index');
        Route::get('/subjects/create', [SubjectController::class, 'create'])->name('subjects.create');
        Route::post('/subjects', [SubjectController::class, 'store'])->name('subjects.store');
        Route::get('/subjects/{subject}/edit', [SubjectController::class, 'edit'])->name('subjects.edit');
        Route::put('/subjects/{subject}', [SubjectController::class, 'update'])->name('subjects.update');
        Route::get('/instructor-assignments', [InstructorAssignmentController::class, 'index'])->name('instructor-assignments.index');
        Route::post('/instructor-assignments', [InstructorAssignmentController::class, 'store'])->name('instructor-assignments.store');
        Route::patch('/instructor-assignments/group/{faculty}/{course}/toggle', [InstructorAssignmentController::class, 'toggleGroup'])->name('instructor-assignments.group-toggle');
        Route::put('/instructor-assignments/group/{faculty}/{course}', [InstructorAssignmentController::class, 'updateGroup'])->name('instructor-assignments.group-update');
        Route::delete('/instructor-assignments/group/{faculty}/{course}', [InstructorAssignmentController::class, 'destroyGroup'])->name('instructor-assignments.group-destroy');
        Route::patch('/instructor-assignments/{assignment}/toggle', [InstructorAssignmentController::class, 'toggle'])->name('instructor-assignments.toggle');
        Route::delete('/instructor-assignments/{assignment}', [InstructorAssignmentController::class, 'destroy'])->name('instructor-assignments.destroy');
    });
    Route::view('/reports','placeholder',['title' => 'Reports'])->middleware('role:admin,program_head')->name('reports');
});
