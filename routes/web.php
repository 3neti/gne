<?php

use App\Http\Controllers\DoctorController;
use App\Http\Controllers\DoctorRosterRequirementController;
use App\Http\Controllers\DocumentSetController;
use App\Http\Controllers\RepositoryWorkbenchController;
use App\Http\Controllers\ResolvedDocumentController;
use App\Http\Controllers\RosterAssignmentController;
use App\Http\Controllers\RosteringDashboardController;
use App\Http\Controllers\RosterPeriodController;
use App\Http\Controllers\RosterStaffingController;
use App\Http\Controllers\ShowCompiledBrowserDocumentController;
use App\Http\Controllers\StoryboardFrameController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', RepositoryWorkbenchController::class)->name('dashboard')->defaults('section', 'dashboard');
    Route::get('repository', RepositoryWorkbenchController::class)->name('repository')->defaults('section', 'repository');
    Route::get('profiles', RepositoryWorkbenchController::class)->name('profiles')->defaults('section', 'profiles');
    Route::get('scenarios', RepositoryWorkbenchController::class)->name('scenarios')->defaults('section', 'scenarios');
    Route::get('artifacts', RepositoryWorkbenchController::class)->name('artifacts')->defaults('section', 'artifacts');
    Route::get('materialization', RepositoryWorkbenchController::class)->name('materialization')->defaults('section', 'materialization');
    Route::get('documents', RepositoryWorkbenchController::class)->name('documents')->defaults('section', 'documents');
    Route::get('document-sets', [DocumentSetController::class, 'index'])->name('document_sets.index');
    Route::get('document-sets/{subject}', [DocumentSetController::class, 'show'])->name('document_sets.show');
    Route::get('documents/{document}/{subject}', ResolvedDocumentController::class)->name('documents.show');
    Route::match(['GET', 'HEAD'], 'subjects/{subject}/documents/{document}/browser', ShowCompiledBrowserDocumentController::class)->name('documents.browser');
    Route::get('storyboards/{storyboard}/frames/{frame}', StoryboardFrameController::class)->name('storyboards.frames.show');

    Route::prefix('rostering')->name('rostering.')->group(function () {
        Route::get('/', RosteringDashboardController::class)->name('dashboard');
        Route::resource('doctors', DoctorController::class)->except(['show']);
        Route::resource('periods', RosterPeriodController::class)->parameters(['periods' => 'roster_period'])->except(['edit', 'destroy']);
        Route::post('periods/{roster_period}/transition', [RosterPeriodController::class, 'transition'])->name('periods.transition');
        Route::get('periods/{roster_period}/staffing', [RosterStaffingController::class, 'edit'])->name('periods.staffing.edit');
        Route::put('periods/{roster_period}/staffing', [RosterStaffingController::class, 'update'])->name('periods.staffing.update');
        Route::get('periods/{roster_period}/requirements', [DoctorRosterRequirementController::class, 'edit'])->name('periods.requirements.edit');
        Route::put('periods/{roster_period}/requirements', [DoctorRosterRequirementController::class, 'update'])->name('periods.requirements.update');
        Route::get('periods/{roster_period}/assignments', [RosterAssignmentController::class, 'index'])->name('periods.assignments.index');
    });
});

require __DIR__.'/settings.php';
