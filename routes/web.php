<?php

use App\Http\Controllers\RepositoryWorkbenchController;
use App\Http\Controllers\ResolvedDocumentController;
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
    Route::get('documents/{document}', ResolvedDocumentController::class)->name('documents.show');
});

require __DIR__.'/settings.php';
